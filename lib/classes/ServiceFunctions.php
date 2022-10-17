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
    public function fetchKangxinRecords($processHistory, $fromRecord = null) {
        $serviceResponse = new ServiceResponse(ServiceResponse::IDLE, 'Process started');
        /* Time between requests to the KNGXIN API to avoid blocking the server */
        $this->apiKangxin->setDelay($GLOBALS['KANGXIN_REQUEST_DELAY']);

        $maxRecords = $GLOBALS['PATIENT_MAX'];
        $page = 1;
        $pageSize = $GLOBALS['PATIENT_PAGE_SIZE'];

        if (is_numeric($fromRecord)) {
            $page = intval($fromRecord / $pageSize) + 1;
        } else {
            $page = 1;
        }

        $totalExpectedRecords = 0;
        $processed = 0;
        $importFailed = 0;
        $newRecords = 0;
        $updatedRecords = 0;
        $ignoredRecords = 0;

        $errMsg = null;

        while ($processed < $maxRecords) {
            try {
                $patientsToImport = $this->apiKangxin->requestPatientList($pageSize, $page);
                $totalExpectedRecords = min($maxRecords, $this->apiKangxin->countTotalExpected());
                ServiceLogger::getInstance()->debug("Patients requested to Kangxin: $pageSize (page $page), received: " . count($patientsToImport));
                $page++;
            } catch (Exception $e) {
                $errMsg = 'ERROR in the request to the Kangxin API: ' . $e->getMessage();
                $processHistory->addLog($errMsg);
                ServiceLogger::getInstance()->error($errMsg);
                break;
            }
            if (count($patientsToImport) < $pageSize) {
                // We have reached the last page, because we received less records than the requested
                $maxRecords = $processed + count($patientsToImport);
            }
            foreach ($patientsToImport as $patientInfo) {
                ServiceLogger::getInstance()->debug(
                        'Processing patient ' . sprintf('%03d', $processed) . ': ' . $patientInfo->getName() . ' (SickId: ' . $patientInfo->getSickId() .
                        ', Id. card: ' . $patientInfo->getIdentityNumber() . ', Episode: ' . $patientInfo->getResidenceNo() . ')', 1);
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
            // Save progress log
            $progress = round(100 * ($totalExpectedRecords ? $processed / $totalExpectedRecords : 1), 1);
            $outputMessage = "Processed: $processed ($progress%), New: $newRecords, Updated: $updatedRecords, Ignored: $ignoredRecords, Failed: $importFailed";
            $processHistory->setOutputMessage($outputMessage);
            $processHistory->save();
        }

        if ($errMsg || $importFailed > 0) {
            $serviceResponse->setCode($serviceResponse::ERROR);
        }

        $outputMessage = "Processed: $processed ($progress%), New: $newRecords, Updated: $updatedRecords, Ignored: $ignoredRecords, Failed: $importFailed";
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

        $maxRecords = min($GLOBALS['PATIENT_MAX'], 1000000);
        $page = 1;
        $pageSize = $GLOBALS['PATIENT_PAGE_SIZE'];
        $processed = 0;
        $importFailed = [];
        $success = [];
        $totalExpectedRecords = RecordPool::countTotalChanged();
        ServiceLogger::getInstance()->debug('Process a maximum of: ' . $maxRecords . ' records');
        while ($processed < $maxRecords) {
            // Retrieve the list of records received from Kangxin marked as "changed"
            $changedRecords = RecordPool::loadChanged($pageSize, $page);

            ServiceLogger::getInstance()->debug("Records requested: $pageSize (page $page), received: " . count($changedRecords));
            $page++;
            if (count($changedRecords) < $pageSize) {
                // We have reached the last page, because we received less records than the requested
                $maxRecords = $processed + count($changedRecords);
            }
            foreach ($changedRecords as $record) {
                $patientInfo = KangxinPatientInfo::fromJson($record->getRecordContent());
                ServiceLogger::getInstance()->debug(
                        'Importing patient ' . sprintf('%03d', $processed) . ': ' . $patientInfo->getName() . ' (SickId: ' . $patientInfo->getSickId() .
                        ', Id. card: ' . $patientInfo->getIdentityNumber() . ', Episode: ' . $patientInfo->getResidenceNo() . ')', 1);
                try {
                    $this->importIntoPHM($patientInfo, $subscription);
                    $success[] = $patientInfo;
                    $record->setChanged(0);
                } catch (Exception $e) {
                    $importFailed[] = $patientInfo;
                    $record->setChanged(2);
                    $processHistory->addLog($e->getMessage());
                    ServiceLogger::getInstance()->error($e->getMessage(), 1);
                }
                $processed++;
                $record->save();
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
        $record = RecordPool::getInstance($kangxinRecord->getSickId(), $kangxinRecord->getResidenceNo());
        if (!$record) {
            $record = new RecordPool($kangxinRecord->getSickId(), $kangxinRecord->getResidenceNo(), $kangxinRecord->getAdmissionTime());
            $record->setRecordContent($kangxinRecord->getOriginalObject());
            $ret = 1;
        } elseif ($record->equals($kangxinRecord->getOriginalObject())) {
            $ret = 0;
        } else {
            $record->setRecordContent($kangxinRecord->getOriginalObject());
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
                if ($admission->getStatus() != APIAdmission::STATUS_DISCHARGED && $kangxinRecord->getDischargeTime() < $GLOBALS['DATE_THRESHOLD']) {
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
     * Tries to find an existing patient that matches the information provided in $importInfo
     *
     * @param KangxinPatientInfo $importInfo
     * @return string
     */
    private function findPatient($importInfo) {
        $casesByIdentifier = [];

        if ($importInfo->getIdentityNumber()) {
            $searchCondition = new StdClass();
            $searchCondition->identifier = new StdClass();
            $searchCondition->identifier->code = $GLOBALS['PATIENT_IDENTIFIER'];
            $searchCondition->identifier->value = $importInfo->getIdentityNumber();
            $casesByIdentifier = $this->apiLK->case_search(json_encode($searchCondition));
        }
        if (!empty($casesByIdentifier)) {
            return $casesByIdentifier[0]->getId();
        }

        return null;
    }

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
            $contactInfo->setGender($importInfo->getSex());
        }
        if ($importInfo->getBirthDate()) {
            $contactInfo->setBirthdate($importInfo->getBirthDate());
        }

        if ($importInfo->getNationCode()) {
            $contactInfo->setNationCode($importInfo->getNationCode());
        }

        if ($importInfo->getPhone()) {
            $phone = new APIContactChannel();
            $phone->setValue($importInfo->getPhone());
            $phone->setCategory('mobile');
            $contactInfo->addPhone($phone);
        }

        if ($importInfo->getCurrentAddress()) {
            $address = new APIContactAddress();
            $address->setAddress($importInfo->getCurrentAddress());
            $contactInfo->addAddress($address);
        }

        if ($importInfo->getIdentityNumber()) {
            $nationalId = new APIIdentifier($GLOBALS['NATIONAL_IDENTIFIER'], $importInfo->getIdentityNumber());
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
        $setupParameters->{KangxinItemCodes::AGE} = $importInfo->getAge();
        $setupParameters->{KangxinItemCodes::CURRENT_ADDRESS} = $importInfo->getCurrentAddress();
        $setupParameters->{KangxinItemCodes::IDENTITY_NUMBER} = $importInfo->getIdentityNumber();
        $setupParameters->{KangxinItemCodes::PHONE} = $importInfo->getPhone();
        $setupParameters->{KangxinItemCodes::CONTACT_NAME} = $importInfo->getContactName();
        $setupParameters->{KangxinItemCodes::RELATION} = $importInfo->getRelation();
        $setupParameters->{KangxinItemCodes::CONTACT_PHONE} = $importInfo->getContactPhone();
        $setupParameters->{KangxinItemCodes::ADMISSION_TIME} = $importInfo->getAdmissionTime();
        $setupParameters->{KangxinItemCodes::ADMISSION_DEPARTMENT} = $importInfo->getAdmissionDepartment();
        $setupParameters->{KangxinItemCodes::ADMISSION_WARD} = $importInfo->getAdmissionWard();
        $setupParameters->{KangxinItemCodes::HOSPITAL_ADMISSION} = $importInfo->getHospitalAdmission();
        $setupParameters->{KangxinItemCodes::DISCHARGE_TIME} = $importInfo->getDischargeTime();
        $setupParameters->{KangxinItemCodes::DISCHARGE_DEPARTMENT} = $importInfo->getDischargeDepartment();
        $setupParameters->{KangxinItemCodes::DISCHARGE_WARD} = $importInfo->getDischargeWard();
        $setupParameters->{KangxinItemCodes::ACTUAL_HOSPITAL_DAYS} = $importInfo->getActualHospitalDays();
        $setupParameters->{KangxinItemCodes::DISCHARGE_DISEASE_CODE} = $importInfo->getDischargeDiseaseCode();
        $setupParameters->{KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS} = $importInfo->getDischargeMainDiagnosis();
        $setupParameters->{KangxinItemCodes::DRUG_ALLERGY} = $importInfo->getDrugAllergy();
        $setupParameters->{KangxinItemCodes::DOCTOR} = $importInfo->getDoctor();
        $setupParameters->{KangxinItemCodes::RESPONSIBLE_NURSE} = $importInfo->getResponsibleNurse();
        $setupParameters->{KangxinItemCodes::HNTH_HOSPITAL} = $importInfo->getNthHospital();
        $setupParameters->{KangxinItemCodes::HOSPITALIZED} = $importInfo->getHospitalized();
        $setupParameters->{KangxinItemCodes::DISCHARGE_SITUATION} = $importInfo->getDischargeSituation();
        $setupParameters->{KangxinItemCodes::DISCHARGE_INSTRUCTIONS} = $importInfo->getDischargeInstructions();
        $setupParameters->{KangxinItemCodes::NOTES} = $importInfo->getNote();
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
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::LAST_UPDATE)) {
            $q->setValue(currentDate());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_ID)) {
            $q->setValue($importInfo->getSickId());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_NUM)) {
            $q->setValue($importInfo->getSickNum());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RESIDENCE_NO)) {
            $q->setValue($importInfo->getResidenceNo());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::AGE)) {
            $q->setValue($importInfo->getAge());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CURRENT_ADDRESS)) {
            $q->setValue($importInfo->getCurrentAddress());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::IDENTITY_NUMBER)) {
            $q->setValue($importInfo->getIdentityNumber());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PHONE)) {
            $q->setValue($importInfo->getPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_NAME)) {
            $q->setValue($importInfo->getContactName());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATION)) {
            $q->setValue($importInfo->getRelation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_PHONE)) {
            $q->setValue($importInfo->getContactPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_TIME)) {
            $q->setValue($importInfo->getAdmissionTime());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_DEPARTMENT)) {
            $q->setValue($importInfo->getAdmissionDepartment());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_WARD)) {
            $q->setValue($importInfo->getAdmissionWard());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITAL_ADMISSION)) {
            $q->setValue($importInfo->getHospitalAdmission());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_TIME)) {
            $q->setValue($importInfo->getDischargeTime());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_DEPARTMENT)) {
            $q->setValue($importInfo->getDischargeDepartment());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_WARD)) {
            $q->setValue($importInfo->getDischargeWard());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ACTUAL_HOSPITAL_DAYS)) {
            $q->setValue($importInfo->getActualHospitalDays());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DRUG_ALLERGY)) {
            $q->setValue($importInfo->getDrugAllergy());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DOCTOR)) {
            $q->setValue($importInfo->getDoctor());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DOCTOR_CODE)) {
            $q->setValue($importInfo->getDoctorCode());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RESPONSIBLE_NURSE)) {
            $q->setValue($importInfo->getResponsibleNurse());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HNTH_HOSPITAL)) {
            $q->setValue($importInfo->getNthHospital());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITALIZED)) {
            $q->setValue($importInfo->getHospitalized());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_SITUATION)) {
            $q->setValue($importInfo->getDischargeSituation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_INSTRUCTIONS)) {
            $q->setValue($importInfo->getDischargeInstructions());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::NOTES)) {
            $q->setValue($importInfo->getNote());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SOURCE)) {
            $q->setValue(2);
            $arrQuestions[] = $q;
        }

        $ix = 1;
        $diagnosis = $importInfo->getDiagnosis();
        if (!empty($diagnosis) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::DIAGNOSIS_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_DISEASE_CODE)) {
                $q->setValue($importInfo->getDischargeDiseaseCode());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
                $q->setValue($importInfo->getDischargeMainDiagnosis());
                $arrQuestions[] = $q;
            }
            $ix++;
            foreach ($diagnosis as $diag) {
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_OTHER_DIAG_CODE)) {
                    $q->setValue($diag->getCode());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_OTHER_DIAGNOSES)) {
                    $q->setValue($diag->getName());
                    $arrQuestions[] = $q;
                }
            }
        }

        $ix = 1;
        $procedures = $importInfo->getProcedures();
        if (!empty($procedures) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::PROCEDURE_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            foreach ($procedures as $procedure) {
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ORDER)) {
                    $q->setValue($procedure->getOrder());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ORDER_DATE)) {
                    $q->setValue($procedure->getOrderDate());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_DATE)) {
                    $q->setValue($procedure->getOperationDate());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_LEVEL)) {
                    $q->setValue($procedure->getOperationLevel());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_SURGEON)) {
                    $q->setValue($procedure->getOperationSurgeon());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_TYPE)) {
                    $q->setValue($procedure->getOperationType());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_CODE)) {
                    $q->setValue($procedure->getOperationCode());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME)) {
                    $q->setValue($procedure->getOperationName());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_1)) {
                    $q->setValue($procedure->getOperationName1());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_2)) {
                    $q->setValue($procedure->getOperationName2());
                    $arrQuestions[] = $q;
                }
                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_3)) {
                    $q->setValue($procedure->getOperationName3());
                    $arrQuestions[] = $q;
                }

                if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::OPERATION_NAME_4)) {
                    $q->setValue($procedure->getOperationName4());
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
}
