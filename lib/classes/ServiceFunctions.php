<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */
class ServiceFunctions {
    /** @var LinkcareSoapAPI */
    private $apiLK;
    /** @var KangxinAPI */
    private $apiKangxin;

    /* Other Constants */
    const PATIENT_HISTORY_TASK_CODE = 'KANGXIN_IMPORT';
    const EPISODE_FORM_CODE = 'KANGXIN_IMPORT_FORM';
    const EPISODE_ID_ITEM_CODE = 'RESIDENCE_NO';
    const KANGXIN_ENROL_SELECT = 'KANGXIN_ENROL_SELECT';

    /**
     *
     * @param LinkcareSoapAPI $apiLK
     * @param KangxinAPI $apiKangxin
     */
    public function __construct($apiLK, $apiKangxin) {
        $this->apiLK = $apiLK;
        $this->apiKangxin = $apiKangxin;
    }

    /**
     * Retrieves the complete list of episodes from Kangxin Hospital and stores the records in an intermediate table indicating which ones have
     * changed
     *
     * @param ProcessHistory $processHistory
     * @return ServiceResponse;
     */
    public function fetchKangxinRecords($processHistory) {
        $serviceResponse = new ServiceResponse(ServiceResponse::IDLE, 'Process started');
        /* Time between requests to the KNGXIN API to avoid blocking the server */
        $this->apiKangxin->setDelay($GLOBALS['KANGXIN_REQUEST_DELAY']);

        $processed = 0;
        $importFailed = 0;
        $newRecords = 0;
        $updatedRecords = 0;
        $ignoredRecords = 0;
        $errMsg = null;

        $fromDate = RecordPool::getLastOperationDate();
        $isFirstLoad = isNullOrEmpty($fromDate);
        if ($fromDate) {
            /*
             * The DB is empty, so this is the first time we are fetching records from Kangxin.
             * We will use the preconfigured minimum date.
             */
            $fromDate = $GLOBALS['MINIMUM_DATE'];
        }

        try {
            $operationsFetched = $this->apiKangxin->requestPatientList($fromDate, $isFirstLoad);
            $maxRecords = count($operationsFetched);
            ServiceLogger::getInstance()->debug("Patients requested to Kangxin from date $fromDate: $maxRecords");
        } catch (Exception $e) {
            $maxRecords = 0;
            $errMsg = 'ERROR in the request to the Kangxin API: ' . $e->getMessage();
            $processHistory->addLog($errMsg);
            ServiceLogger::getInstance()->error($errMsg);
        }

        $page = 1;
        $pageSize = 20;
        while ($processed < $maxRecords) {
            // We will process the records by pages to update the ProcessHistory so that a progress message is added per each page processed
            $patientsToImport = array_slice($operationsFetched, ($page - 1) * $pageSize, $pageSize);

            foreach ($patientsToImport as $patientInfo) {
                ServiceLogger::getInstance()->debug(
                        'Processing patient ' . sprintf('%03d', $processed) . ': ' . $patientInfo->getName() . ' (SickId: ' . $patientInfo->getSickId() .
                        ', Id. card: ' . $patientInfo->getIdCard() . ', Episode: ' . $patientInfo->getResidenceNo() . ', ApplyOperatNo: ' .
                        $patientInfo->getApplyOperatNo() . ')', 1);
                try {
                    $ret = $this->processFetchedRecord($patientInfo);
                    switch ($ret) {
                        case 0 :
                            $ignoredRecords++;
                            break;
                        case 1 :
                            $newRecords++;
                            break;
                        case 2 :
                            $updatedRecords++;
                            break;
                    }
                } catch (Exception $e) {
                    $importFailed++;
                    $processHistory->addLog($e->getMessage());
                    ServiceLogger::getInstance()->error($e->getMessage(), 2);
                }
                $processed++;
                if ($processed >= $maxRecords) {
                    break;
                }
            }
            $page++;

            // Save progress log
            $progress = round(100 * ($maxRecords ? $processed / $maxRecords : 1), 1);
            $outputMessage = "Processed from $fromDate: $processed ($progress%), New: $newRecords, Updated: $updatedRecords, Ignored: $ignoredRecords, Failed: $importFailed";
            $processHistory->setOutputMessage($outputMessage);
            $processHistory->save();
        }

        if ($errMsg || $importFailed > 0) {
            $serviceResponse->setCode($serviceResponse::ERROR);
        }

        $outputMessage = "Processed from $fromDate: $processed ($progress%), New: $newRecords, Updated: $updatedRecords, Ignored: $ignoredRecords, Failed: $importFailed";
        $serviceResponse->setMessage($outputMessage);
        return $serviceResponse;
    }

    /**
     * Sends a request to Kangxin Hospital to retrieve the list of patients that should be imported in the Linkcare Platform
     *
     * @param ProcessHistory $processHistory
     * @return ServiceResponse
     */
    public function importPatients($processHistory) {
        $serviceResponse = new ServiceResponse(ServiceResponse::IDLE, 'Process started');
        try {
            $subscription = $this->apiLK->subscription_get($GLOBALS['PROGRAM_CODE'], $GLOBALS['TEAM_CODE']);
        } catch (Exception $e) {
            $serviceResponse->setCode(ServiceResponse::ERROR);
            $serviceResponse->setMessage(
                    'ERROR LOADING SUBSCRIPTION (Program: ' . $GLOBALS['PROGRAM_CODE'] . ', Team: ' . $GLOBALS['TEAM_CODE'] .
                    ') FOR IMPORTING PATIENTS: ' . $e->getMessage());
            $processHistory->addLog($serviceResponse->getMessage());
            return $serviceResponse;
        }

        $maxRecords = $GLOBALS['PATIENT_MAX'];
        if ($maxRecords <= 0) {
            $maxRecords = 10000000;
        }

        $page = 1;
        $pageSize = $GLOBALS['PATIENT_PAGE_SIZE'];
        $processed = 0;
        $importFailed = [];
        $success = [];
        $totalExpectedRecords = RecordPool::countTotalChanged();
        ServiceLogger::getInstance()->debug('Process a maximum of: ' . $maxRecords . ' records');
        while ($processed < $maxRecords) {
            // Retrieve the list of episodes received from Kangxin marked as "changed"
            $changedRecords = RecordPool::loadChanged($pageSize, $page);

            ServiceLogger::getInstance()->debug("Records requested: $pageSize (page $page), received: " . count($changedRecords));
            $page++;
            if (count($changedRecords) < $pageSize) {
                // We have reached the last page, because we received less records than the requested
                $maxRecords = $processed + count($changedRecords);
            }
            foreach ($changedRecords as $episodeOperations) {
                usort($episodeOperations,
                        function ($op1, $op2) {
                            /* @var KangxinProcedure $op1 */
                            /* @var KangxinProcedure $op2 */
                            if ($op1->getOperationDate() > $op2->getOperationDate()) {
                                return -1;
                            } elseif ($op1->getOperationDate() < $op2->getOperationDate()) {
                                return 1;
                            }
                            return 0;
                        });
                // The first opeartion of the list is the most recent one, so it contains the most updated information
                /* @var RecordPool $lastOperation */
                $lastOperation = reset($episodeOperations);
                /* @var RecordPool[] $otherOperations */
                $otherOperations = array_slice($episodeOperations, 1); //
                $patientInfo = KangxinPatientInfo::fromJson($lastOperation->getRecordContent());
                foreach ($otherOperations as $record) {
                    // The episode contains more than one operation. Add it to the Patient info
                    $patientInfo->addOperation($record->getRecordContent());
                }
                ServiceLogger::getInstance()->debug(
                        'Importing patient ' . sprintf('%03d', $processed) . ': ' . $patientInfo->getName() . ' (SickId: ' . $patientInfo->getSickId() .
                        ', Id. card: ' . $patientInfo->getIdCard() . ', Episode: ' . $patientInfo->getResidenceNo() . ')', 1);
                try {
                    $this->importIntoPHM($patientInfo, $subscription);
                    $success[] = $patientInfo;
                    foreach ($episodeOperations as $record) {
                        $record->setChanged(0);
                    }
                } catch (Exception $e) {
                    $importFailed[] = $patientInfo;
                    foreach ($episodeOperations as $record) {
                        $record->setChanged(2);
                    }
                    $processHistory->addLog($e->getMessage());
                    ServiceLogger::getInstance()->error($e->getMessage(), 1);
                }
                $processed++;
                foreach ($episodeOperations as $record) {
                    $record->save();
                }
                if ($processed >= $maxRecords) {
                    break;
                }
            }

            $progress = round(100 * ($totalExpectedRecords ? $processed / $totalExpectedRecords : 1), 1);

            $outputMessage = "Processed: $processed ($progress%), Success: " . count($success) . ', Failed: ' . count($importFailed);
            // Save progress log
            $processHistory->setOutputMessage($outputMessage);
            $processHistory->save();
        }

        $outputMessage = "Processed: $processed ($progress%), Success: " . count($success) . ', Failed: ' . count($importFailed);
        if (count($success) + count($importFailed) == 0) {
            $outputStatus = ServiceResponse::IDLE;
        } elseif (count($importFailed) > 0) {
            $outputStatus = ServiceResponse::ERROR;
        } else {
            $outputStatus = ServiceResponse::SUCCESS;
        }

        $serviceResponse->setCode($outputStatus);
        $serviceResponse->setMessage($outputMessage);
        return $serviceResponse;
    }

    /**
     *
     * Stores an episode record in the local DB.
     * Possible return values:
     * <ul>
     * <li>0: Record ignored because the record already existed and has not changed</li>
     * <li>1: The record is new</li>
     * <li>2: The record already existed but it has been updated</li>
     * </ul>
     *
     * @param KangxinPatientInfo $kangxinRecord
     * @throws ServiceException
     * @return int
     */
    private function processFetchedRecord($kangxinRecord) {
        /** @var KangxinProcedure $operation */
        $operation = reset($kangxinRecord->getProcedures());
        $lastUpdate = isNullOrEmpty($kangxinRecord->getUpdateTime()) ? $operation->getOperationDate() : $kangxinRecord->getUpdateTime();

        $record = RecordPool::getInstance($kangxinRecord->getSickId(), $kangxinRecord->getResidenceNo(), $kangxinRecord->getApplyOperatNo());
        if (!$record) {
            $record = new RecordPool($kangxinRecord->getSickId(), $kangxinRecord->getResidenceNo(), $kangxinRecord->getApplyOperatNo());
            $record->setAdmissionDate($kangxinRecord->getAdmissionTime());
            $record->setOperationDate($operation->getOperationDate());
            $record->setRecordContent($kangxinRecord->getOriginalObject());
            $record->setLastUpdate($lastUpdate);
            $ret = 1;
        } elseif ($record->equals($kangxinRecord->getOriginalObject())) {
            $ret = 0;
        } else {
            $record->setAdmissionDate($kangxinRecord->getAdmissionTime());
            $record->setOperationDate($operation->getOperationDate());
            $record->setRecordContent($kangxinRecord->getOriginalObject());
            $record->setLastUpdate($lastUpdate);
            $ret = 2;
        }

        $error = $record->save();
        if ($error && $error->getCode()) {
            // Error saving the record
            throw new ServiceException(ErrorCodes::DB_ERROR, $error->getCode() . ' - ' . $error->getMessage());
        }

        return $ret;
    }

    /**
     * Imports or updates a patient fecthed from Kangxin into the Linkcare Platform
     *
     * @param KangxinPatientInfo $kangxinRecord
     */
    private function importIntoPHM($kangxinRecord, $subscription) {
        $errMsg = '';
        $errCode = null;

        // Create or update the Patient in Linkcare platform
        try {
            $patient = $this->createPatient($kangxinRecord, $subscription);
        } catch (ServiceException $se) {
            $errMsg = 'Import service generated an exception: ' . $se->getMessage();
            $errCode = $se->getCode();
        } catch (APIException $ae) {
            $errMsg = 'Linkcare API returned an exception: ' . $ae->getMessage();
            $errCode = $ae->getCode();
        } catch (Exception $e) {
            $errMsg = 'Unexpected exception: ' . $e->getMessage();
            $errCode = ErrorCodes::UNEXPECTED_ERROR;
        }
        if ($errMsg) {
            $errMsg = 'ERROR CREATING PATIENT ' . $kangxinRecord->getName() . '(' . $kangxinRecord->getSickId() . '): ' . $errMsg;
            throw new ServiceException($errCode, $errMsg);
        }

        // Create a new Admission for the patient or find the existing one
        try {
            // Check if the Admission already exists
            $admission = $this->findAdmission($patient, $kangxinRecord, $subscription);
            if (!$admission) {
                $admission = $this->createAdmission($patient, $kangxinRecord, $subscription);
            }
            $this->updateEpisodeData($admission, $kangxinRecord);
            // Discharge the Admission if necessary
            if ($kangxinRecord->getDischargeTime()) {
                if ($admission->getStatus() != APIAdmission::STATUS_DISCHARGED &&
                        $kangxinRecord->getDischargeTime() < $GLOBALS['DISCHARGE_DATE_THRESHOLD']) {
                    $admission->discharge(null, $kangxinRecord->getDischargeTime());
                } elseif ($admission->getStatus() == APIAdmission::STATUS_DISCHARGED &&
                        $admission->getDischargeDate() != $kangxinRecord->getDischargeTime()) {
                    // The ADMISSION was discharged, but the date has changed
                    $admission->setDischargeDate();
                    $admission->save();
                }
            }
            if ($admission->getStatus() != APIAdmission::STATUS_DISCHARGED) {
                $this->createSelectionTask($admission);
            }
        } catch (ServiceException $se) {
            $errMsg = 'Import service generated an exception: ' . $se->getMessage();
            $errCode = $se->getCode();
        } catch (APIException $ae) {
            $errMsg = 'Linkcare API returned an exception: ' . $ae->getMessage();
            $errCode = $ae->getCode();
        } catch (Exception $e) {
            $errMsg = 'Unexpected exception: ' . $e->getMessage();
            $errCode = ErrorCodes::UNEXPECTED_ERROR;
        }
        if ($errMsg) {
            $errMsg = 'ERROR CREATING/UPDATING ADMISSION FOR PATIENT ' . $kangxinRecord->getName() . '(' . $kangxinRecord->getIdentityNumber() . '): ' .
                    $errMsg;
            throw new ServiceException($errCode, $errMsg);
        }
    }

    /**
     * ******************************** INTERNAL FUNCTIONS *********************************
     */

    /**
     * Creates a new patient in Linkcare database using as reference the information in $importInfo
     *
     * @param KangxinPatientInfo $importInfo
     * @param APISubscription $subscription
     * @return APICase
     */
    private function createPatient($importInfo, $subscription) {
        // Create the case
        $contactInfo = new APIContact();

        if ($importInfo->getName()) {
            $contactInfo->setCompleteName($importInfo->getName());
        }
        if ($importInfo->getSex()) {
            $contactInfo->setGender($importInfo->getSex([$this, 'mapSexValue']));
        }
        if ($importInfo->getBirthDate()) {
            $contactInfo->setBirthdate($importInfo->getBirthDate());
        }

        if ($importInfo->getNationality()) {
            $contactInfo->setNationCode($importInfo->getNationality());
        }

        if ($importInfo->getPhone()) {
            $phone = new APIContactChannel();
            $phone->setValue($importInfo->getPhone());
            $phone->setCategory('mobile');
            $contactInfo->addPhone($phone);
        }

        if ($importInfo->getIdCard() && ($identifierName = self::IdentifierNameFromCardType($importInfo->getIdCardType()))) {
            ;
            $nationalId = new APIIdentifier($identifierName, $importInfo->getIdCard());
            $contactInfo->addIdentifier($nationalId);
        }
        if ($importInfo->getSickId()) {
            $patientId = new APIIdentifier($GLOBALS['PATIENT_IDENTIFIER'], $importInfo->getSickId());
            $patientId->setTeamId($GLOBALS['PATIENT_IDENTIFIER_TEAM']);
            $contactInfo->addIdentifier($patientId);
        }

        // Create a new CASE with incomplete data (only the KIT_ID)
        $patientId = $this->apiLK->case_insert($contactInfo, $subscription ? $subscription->getId() : null, true);
        return $this->apiLK->case_get($patientId);
    }

    /**
     * Search an existing Admission that corresponds to the selected Kanxin episode
     *
     * @param APICase $case
     * @param KangxinPatientInfo $importInfo
     * @param APISubscription $subscription
     */
    private function findAdmission($case, $importInfo, $subscription) {
        $filter = new TaskFilter();
        $filter->setSubscriptionIds($subscription->getId());
        $filter->setTaskCodes(self::PATIENT_HISTORY_TASK_CODE);
        $episodeList = $case->getTaskList(1000, 0, $filter);

        /*
         * First of all we need to find out which is the TASK that corresponds to the episode informed.
         * Depending on the result of the search can:
         * - If the TASK was found, update its contents with the new information
         * - If the TASK was not found, create a new one
         */

        if (empty($episodeList)) {
            return null;
        }
        $foundEpisodeTask = null;
        foreach ($episodeList as $episodeTask) {
            $episodeForms = $episodeTask->findForm(self::EPISODE_FORM_CODE);
            foreach ($episodeForms as $form) {
                $item = $form->findQuestion(self::EPISODE_ID_ITEM_CODE);
                if ($item->getValue() == $importInfo->getResidenceNo()) {
                    // The episode already exists
                    $foundEpisodeTask = $episodeTask;
                    break;
                }
            }
        }

        if ($foundEpisodeTask) {
            // There already exists a Task for the Kangxin episode
            return $this->apiLK->admission_get($foundEpisodeTask->getAdmissionId());
        }
        return null;
    }

    /**
     * Creates a new Admission for a patient
     *
     * @param APICase $case
     * @param KangxinPatientInfo $importInfo
     * @param APISubscription $subscription
     * @return APIAdmission
     */
    private function createAdmission($case, $importInfo, $subscription) {
        $setupParameters = new stdClass();

        $setupParameters->{KangxinItemCodes::SICK_ID} = $importInfo->getSickId();
        $setupParameters->{KangxinItemCodes::SICK_NUM} = $importInfo->getSickNum();
        $setupParameters->{KangxinItemCodes::RESIDENCE_NO} = $importInfo->getResidenceNo();
        $setupParameters->{KangxinItemCodes::SOURCE} = 2;

        return $this->apiLK->admission_create($case->getId(), $subscription->getId(), $importInfo->getAdmissionTime(), null, true, $setupParameters);
    }

    /**
     * Updates the information related with a specific episode of the patient.
     * There exists a TASK with TASK_CODE = XXXXX for each episode
     */
    /**
     *
     * @param APIAdmission $admission
     * @param KangxinPatientInfo $importInfo
     */
    private function updateEpisodeData($admission, $importInfo) {
        $filter = new TaskFilter();
        $filter->setTaskCodes(self::PATIENT_HISTORY_TASK_CODE);
        $episodeList = $admission->getTaskList(1, 0, $filter);

        /*
         * First of all we need to find out which is the TASK that corresponds to the episode informed.
         * Depending on the result of the search can:
         * - If the TASK was found, update its contents with the new information
         * - If the TASK was not found, create a new one
         */
        $episodeTask = reset($episodeList);

        if (!$episodeTask) {
            /* We need to create a new TASK to store the episode information */
            $episodeTask = $admission->insertTask(self::PATIENT_HISTORY_TASK_CODE, $importInfo->getAdmissionTime());
        }

        $episodeForms = $episodeTask->findForm(self::EPISODE_FORM_CODE);
        foreach ($episodeForms as $form) {
            $item = $form->findQuestion(self::EPISODE_ID_ITEM_CODE);
            if (!$item->getValue()) {
                $emptyForm = $form;
            } elseif ($item->getValue() == $importInfo->getResidenceNo()) {
                // The episode already exists
                $episodeInfoForm = $form;
                break;
            }
        }

        $episodeInfoForm = $episodeInfoForm ?? $emptyForm;

        if (!$episodeInfoForm) {
            // The FORM for storing the Episode information does not exist and there is no one empty to use. We need to create a new one in the TASK
            $activities = $episodeTask->activityInsert(self::PATIENT_HISTORY_TASK_CODE);
            foreach ($activities as $act) {
                if (!$act instanceof APIForm) {
                    continue;
                }
                if ($act->getFormCode() == self::EPISODE_FORM_CODE) {
                    $episodeInfoForm = $act;
                    break;
                }
            }
        }

        if (!$episodeInfoForm) {
            // Error: could not create a new FORM to store the episode information
            throw new ServiceException(ErrorCodes::API_COMM_ERROR, 'The FORM with FORM_CODE = ' . self::EPISODE_FORM_CODE . ' (from the TASK_TEMPLATE ' .
                    self::PATIENT_HISTORY_TASK_CODE . ') to store the information of a patient was not inserted');
        }

        $arrQuestions = [];
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::LAST_IMPORT)) {
            $q->setAnswer(currentDate());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::LAST_UPDATE)) {
            $q->setAnswer($importInfo->getUpdateTime());
            $arrQuestions[] = $q;
        }

        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_ID)) {
            $q->setAnswer($importInfo->getSickId());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_NUM)) {
            $q->setAnswer($importInfo->getSickNum());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ETHNICITY)) {
            $q->setAnswer($importInfo->getEthnicity());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ETHNICITY_OPTIONS)) {
            // Select the option by its value
            $q->setValue($importInfo->getEthnicity());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ID_CARD)) {
            $q->setAnswer($importInfo->getIdCard());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ID_CARD_TYPE)) {
            $q->setAnswer($importInfo->getIdCardType());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PHONE)) {
            $q->setAnswer($importInfo->getPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::MARITAL)) {
            $q->setAnswer($importInfo->getMarital());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::MARITAL_OPTIONS)) {
            // Select the option by its value
            $q->setValue($importInfo->getMarital());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PATIENT_TYPE)) {
            $q->setAnswer($importInfo->getKind());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PROFESSION)) {
            $q->setAnswer($importInfo->getProfession());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PROFESSION_OPTIONS)) {
            // Select the option by its value
            $q->setValue($importInfo->getProfession());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_NAME)) {
            $q->setAnswer($importInfo->getContactName());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATION)) {
            $q->setAnswer($importInfo->getRelation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATION_OPTIONS)) {
            // Select the option by its value
            $q->setValue($importInfo->getRelation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATIONSHIP_CODE)) {
            // Select the option by its value
            $q->setValue($importInfo->getRelation([$this, 'mapRelationshipCodeValue']));
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATIVE_FAMILY_CODE)) {
            $q->setAnswer($importInfo->getRelation([$this, 'mapRelativeCodeValue']));
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_PHONE)) {
            $q->setAnswer($importInfo->getContactPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_DIAG)) {
            $q->setAnswer($importInfo->getAdmissionDiag());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITAL_ADMISSION)) {
            $q->setAnswer($importInfo->getHospitalAdmission());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_DIAG)) {
            $q->setAnswer($importInfo->getDischargeDiag());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS_CODE)) {
            $q->setAnswer($importInfo->getDischargeDiseaseCode());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
            $q->setAnswer($importInfo->getDischargeMainDiagnosis());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DRUG_ALLERGY)) {
            $q->setAnswer($importInfo->getDrugAllergy());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITALIZED)) {
            $q->setAnswer($importInfo->getHospitalized());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_SITUATION)) {
            $q->setAnswer($importInfo->getDischargeSituation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_INSTRUCTIONS)) {
            $q->setAnswer($importInfo->getDischargeInstructions());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SOURCE)) {
            $q->setAnswer(2);
            $arrQuestions[] = $q;
        }

        // General Admission information stored as a table (1 row)
        $ix = 1;
        if (($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_INFO_TABLE)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::RESIDENCE_NO)) {
                $q->setAnswer($importInfo->getResidenceNo());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::VISIT_NUMBER)) {
                $q->setAnswer($importInfo->getVisitNumber());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ADMISSION_TIME)) {
                $q->setAnswer($importInfo->getAdmissionTime());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ADMISSION_DEPARTMENT)) {
                $q->setAnswer($importInfo->getAdmissionDept());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_TIME)) {
                $q->setAnswer($importInfo->getDischargeTime());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_DEPARTMENT)) {
                $q->setAnswer($importInfo->getDischargeDept());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_STATUS)) {
                $q->setAnswer($importInfo->getDischargeStatus());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_STATUS_OPTIONS)) {
                $q->setValue($importInfo->getDischargeStatus());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DOCTOR)) {
                $q->setAnswer($importInfo->getDoctor());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DOCTOR_CODE)) {
                $q->setAnswer($importInfo->getDoctorCode());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::RESPONSIBLE_NURSE)) {
                $q->setAnswer($importInfo->getResponsibleNurse());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::RESPONSIBLE_NURSE_CODE)) {
                $q->setAnswer($importInfo->getResponsibleNurseCode());
                $arrQuestions[] = $q;
            }
        }

        $ix = 1;
        $diagnosis = $importInfo->getDiagnosis();
        if (!empty($diagnosis) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::DIAGNOSIS_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS_CODE)) {
                $q->setAnswer($importInfo->getDischargeDiseaseCode());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
                $q->setAnswer($importInfo->getDischargeMainDiagnosis());
                $arrQuestions[] = $q;
            }
            $ix++;
            foreach ($diagnosis as $diag) {
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_OTHER_DIAG_CODE)) {
                    $q->setAnswer($diag->getCode());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_OTHER_DIAGNOSES)) {
                    $q->setAnswer($diag->getName());
                    $arrQuestions[] = $q;
                }
            }
        }

        $ix = 1;
        $procedures = $importInfo->getProcedures();
        if (!empty($procedures) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::PROCEDURE_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            foreach ($procedures as $procedure) {
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_ID)) {
                    $q->setAnswer($procedure->getApplyOperatNo());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::PROCESS_ORDER)) {
                    $q->setAnswer($procedure->getProcessOrder());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_DATE)) {
                    $q->setAnswer($procedure->getOperationDate());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_LEVEL)) {
                    $q->setAnswer($procedure->getOperationLevel());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_DOCTOR)) {
                    $q->setAnswer($procedure->getOperationDoctor());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_DOCTOR_CODE)) {
                    $q->setAnswer($procedure->getOperationDoctorCode());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_TYPE)) {
                    $q->setAnswer($procedure->getOperationType());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_CODE)) {
                    $q->setAnswer($procedure->getOperationCode());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME)) {
                    $q->setAnswer($procedure->getOperationName());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_1)) {
                    $q->setAnswer($procedure->getOperationName1());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_2)) {
                    $q->setAnswer($procedure->getOperationName2());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_3)) {
                    $q->setAnswer($procedure->getOperationName3());
                    $arrQuestions[] = $q;
                }

                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_4)) {
                    $q->setAnswer($procedure->getOperationName4());
                    $arrQuestions[] = $q;
                }

                $ix++;
            }
        }

        if (!empty($arrQuestions)) {
            $this->apiLK->form_set_all_answers($episodeInfoForm->getId(), $arrQuestions, true);
        }

        if ($importInfo->getAdmissionTime()) {
            $dateParts = explode(' ', $importInfo->getAdmissionTime());
            $date = $dateParts[0];
            $time = $dateParts[1];
            $episodeTask->setDate($date);
            $episodeTask->setHour($time);
            $episodeTask->save();
        }
    }

    /**
     * Creates the TASK (if not created yet) for the Case Manager to select a patient for the PCI Discharge program
     *
     * @param APIAdmission $admission
     */
    private function createSelectionTask($admission) {
        $filter = new TaskFilter();
        $filter->setTaskCodes(self::KANGXIN_ENROL_SELECT);
        $existingTasks = $admission->getTaskList(1, 0, $filter);

        if (count($existingTasks) > 0) {
            // Not necessary to insert the TASK because it already exists
            return;
        }
        $admission->insertTask(self::KANGXIN_ENROL_SELECT);
    }

    /**
     * Maps a Sex value to standard value used in Linkcare Platform
     *
     * @param string $value
     * @return number
     */
    public function mapSexValue($value) {
        if (in_array($value, ['0', '男', 'm', 'M'])) {
            $value = 'M';
        } elseif ($value) {
            $value = 'F';
        }
        return $value;
    }

    /**
     * Maps a Relation value to obtain the corresponing RELATIONSHIP CODE
     *
     * @param string $value
     * @return number
     */
    public function mapRelationshipCodeValue($value) {
        switch ($value) {
            case 1 : // spouse
            case 2 : // son
            case 3 : // Female
            case 4 : // (outside) grandson/daughter
            case 5 : // parents
            case 6 : // (foreign) grandparents
            case 7 : // brothers and sisters
                return 'FAMILY';
            case 91 : // colleague
                return 'WORKMATE';
            case 92 : // friend
                return 'FRIEND';
            case 8 : // other
            case 93 : // lead
            case 94 : // party responsible
                return null;
        }
        return null;
    }

    /**
     * Maps a Relation value to obtain the corresponing RELATIVE_FAMILY CODE
     *
     * @param string $value
     * @return number
     */
    public function mapRelativeCodeValue($value) {
        switch ($value) {
            case 1 : // spouse
                return 'SPOUSE';
            case 2 : // son
                return 'CHILD';
            case 3 : // Female
                return 'CHILD';
            case 4 : // (outside) grandson/daughter
                return 'CHILD.CHILD';
            case 5 : // parents
                return 'PARENT';
            case 6 : // (foreign) grandparents
                return 'PARENT.PARENT';
            case 7 : // brothers and sisters
                return 'SIBLING';
        }
        return null;
    }

    static private function IdentifierNameFromCardType($cardType) {
        switch ($cardType) {
            case '01' : // Chinese ID
                return 'NAT_ZH';
            case '02' : // Chinese Military ID
                return 'NAT_ZH_MIL';
            case '03' : // Passport
                return 'PASS';
            case '04' : // Other
                /* It is not a good idea to have an "OTHER" IDENTIFIER. it is not possible to guarantee whether it will have a unique value */
                return 'OTHER';
            case '05' : // Chinese Household ID
                return 'NAT_ZH_HOUSEHOLD';
            case '06' : // Alien Residence Permit
                return 'NAT_ZH_FOREIGNERS';
            case '07' : // Mainland Travel Permit for Hong Kong and Macao Residents
                return 'NAT_ZH_HK_MACAO';
            case '08' : // Mainland Travel Permit for Taiwan Residents
                return 'NAT_ZH_TAIWAN';
        }
        return null;
    }
}
