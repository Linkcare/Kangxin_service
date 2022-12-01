<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */
class ServiceFunctions {
    /** @var LinkcareSoapAPI */
    private $apiLK;
    /** @var KangxinAPI */
    private $apiKangxin;

    /** @var APIUser[] List of professionals cached in memory */
    private $professionals = [];

    /* Other Constants */
    const PATIENT_HISTORY_TASK_CODE = 'KANGXIN_IMPORT';
    const EPISODE_FORM_CODE = 'KANGXIN_IMPORT_FORM';
    const KANGXIN_OPERATION_TASK_CODE = 'KANGXIN_OPERATION_DATA';
    const KANGXIN_OPERATION_FORM_CODE = 'KANGXIN_OPERATION_DATA_FORM';
    const EPISODE_CHANGE_EVENT_CODE = 'EVENT_EPISODE_UPDATE';

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
        if (!$fromDate) {
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

        // Verify the existence of the required SUBSCRIPTIONS
        try {
            // Locate the SUBSCRIPTION for storing episode information
            $kxEpisodesSubscription = $this->apiLK->subscription_get($GLOBALS['KANGXIN_EPISODES_PROGRAM_CODE'], $GLOBALS['TEAM_CODE']);
        } catch (Exception $e) {
            $serviceResponse->setCode(ServiceResponse::ERROR);
            $serviceResponse->setMessage(
                    'ERROR LOADING SUBSCRIPTION (Care plan: ' . $GLOBALS['KANGXIN_EPISODES_PROGRAM_CODE'] . ', Team: ' . $GLOBALS['TEAM_CODE'] .
                    ') FOR IMPORTING PATIENTS: ' . $e->getMessage());
            $processHistory->addLog($serviceResponse->getMessage());
            return $serviceResponse;
        }

        try {
            // Locate the SUBSCRIPTION of the "Discharge followup" PROGRAM for creating new ADMISSIONS of a patient
            $dschFollowupSubscription = $this->apiLK->subscription_get($GLOBALS['DISCHARGE_FOLLOWUP_PROGRAM_CODE'], $GLOBALS['TEAM_CODE']);
        } catch (Exception $e) {
            $serviceResponse->setCode(ServiceResponse::ERROR);
            $serviceResponse->setMessage(
                    'ERROR LOADING SUBSCRIPTION (Care plan: ' . $GLOBALS['DISCHARGE_FOLLOWUP_PROGRAM_CODE'] . ', Team: ' . $GLOBALS['TEAM_CODE'] .
                    ') FOR DISCHARGE FOLLOWUP PATIENTS: ' . $e->getMessage());
            $processHistory->addLog($serviceResponse->getMessage());
            return $serviceResponse;
        }

        $MaxEpisodes = $GLOBALS['PATIENT_MAX'];
        if ($MaxEpisodes <= 0) {
            $MaxEpisodes = 10000000;
        }

        $page = 1;
        $pageSize = $GLOBALS['PATIENT_PAGE_SIZE'];
        $processed = 0;
        $importFailed = [];
        $success = [];
        $totalExpectedEpisodes = RecordPool::countTotalChanged();
        ServiceLogger::getInstance()->debug('Process a maximum of: ' . $MaxEpisodes . ' episodes');

        // Reset import errors from previous executions and try to process again
        RecordPool::resetErrors();

        // Start process loop
        while ($processed < $MaxEpisodes) {
            /*
             * Retrieve the list of episodes received from Kangxin marked as "changed"
             * We always request for page 1 because as long as we process each page, the processed episodes are marked as "not changed", so when we do
             * the next request, the rest of episodes have shifted to the first page
             */
            $changedEpisodes = RecordPool::loadChanged($pageSize, 1);

            ServiceLogger::getInstance()->debug("Episodes requested: $pageSize (page $page), received: " . count($changedEpisodes));
            $page++;
            if (count($changedEpisodes) < $pageSize) {
                // We have reached the last page, because we received less episodes than the requested
                $MaxEpisodes = $processed + count($changedEpisodes);
            }
            foreach ($changedEpisodes as $episodeOperations) {
                /** @var RecordPool[] $episodeOperations */
                /*
                 * The information received from each clinical episode consists on several records, where each record contains the
                 * information about one intervention.
                 * First of all we will create a single Patient object with all the operations of the episode.
                 * Additionally, whenever we receive updated information about the episode, we want to track the changes received so that we can
                 * inform the Case Manager about wich things have changed. For that reason, we have a copy of the last information loaded in the PHM
                 * so that we can compare with the new information.
                 */
                $prevKangxinData = array_filter(
                        array_map(function ($op) {
                            /** @var RecordPool $op */
                            return $op->getPrevRecordContent();
                        }, $episodeOperations));

                $patientInfo = null;

                $kangxinData = array_map(function ($x) {
                    /** @var RecordPool $x */
                    return $x->getRecordContent();
                }, $episodeOperations);

                if (!empty($prevKangxinData)) {
                    // This clinical episode was been processed before, so first we load the previous information
                    $patientInfo = KangxinPatientInfo::fromJson($prevKangxinData);
                    /*
                     * Now update the information that we already had about the episode with the new information received. This allows to keep track
                     * of the changes
                     */
                    $patientInfo->update($kangxinData);
                } else {
                    // This is the first time we receive information about a clinical episode for this patient
                    $patientInfo = KangxinPatientInfo::fromJson($kangxinData);
                }

                ServiceLogger::getInstance()->debug(
                        'Importing patient ' . sprintf('%03d', $processed) . ': ' . $patientInfo->getName() . ' (SickId: ' . $patientInfo->getSickId() .
                        ', Id. card: ' . $patientInfo->getIdCard() . ', Episode: ' . $patientInfo->getResidenceNo() . ')', 1);
                try {
                    $this->importIntoPHM($patientInfo, $kxEpisodesSubscription, $dschFollowupSubscription);
                    $success[] = $patientInfo;
                    foreach ($episodeOperations as $record) {
                        // Preserve the informat¡on successfully imported so that we can track changes when updated information is received
                        $record->setPrevRecordContent($record->getRecordContent());
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
                if ($processed >= $MaxEpisodes) {
                    break;
                }
            }

            $progress = round(100 * ($totalExpectedEpisodes ? $processed / $totalExpectedEpisodes : 1), 1);

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
     * @param KangxinPatientInfo $kxEpisodeInfo
     * @param APISubscription $kxEpisodeSubscription
     * @param APISubscription $dschFollowupSubscription
     */
    private function importIntoPHM($kxEpisodeInfo, $kxEpisodeSubscription, $dschFollowupSubscription) {
        $errMsg = '';
        $errCode = null;

        // Create or update the Patient in Linkcare platform
        try {
            $patient = $this->createPatient($kxEpisodeInfo);
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
            $errMsg = 'ERROR CREATING PATIENT ' . $kxEpisodeInfo->getName() . '(' . $kxEpisodeInfo->getSickId() . '): ' . $errMsg;
            throw new ServiceException($errCode, $errMsg);
        }

        // Create a new Admission for the patient or find the existing one
        try {
            // Check whether the Admission already exists
            $admission = $this->findAdmission($patient, $kxEpisodeInfo, $kxEpisodeSubscription);
            if (!$admission) {
                ServiceLogger::getInstance()->debug('Creating new Admission for patient in Kangxin Admissions care plan', 2);
                $admission = $this->createEpisodeAdmission($patient, $kxEpisodeInfo, $kxEpisodeSubscription);
                $isNewEpisode = true;
            } else {
                $isNewEpisode = false;
                ServiceLogger::getInstance()->debug('Using existing Admission for patient in Kangxin Admissions care plan', 2);
            }

            $referral = null;
            if ($kxEpisodeInfo->getDoctorCode()) {
                $referral = $this->createProfessional($kxEpisodeInfo->getDoctorCode(), $kxEpisodeInfo->getDoctorName(), $GLOBALS['CASE_MANAGERS_TEAM'],
                        APIRole::CASE_MANAGER);
            }

            $episodeInfoForm = $this->updateEpisodeData($admission, $kxEpisodeInfo, $referral);
            $this->updateOperationTasks($admission, $kxEpisodeInfo);

            $admissionModified = false;
            if ($kxEpisodeInfo->getDischargeTime()) {
                // Discharge the Admission if necessary
                if ($admission->getStatus() != APIAdmission::STATUS_DISCHARGED) {
                    $admission->discharge(null, $kxEpisodeInfo->getDischargeTime());
                } elseif ($admission->getStatus() == APIAdmission::STATUS_DISCHARGED &&
                        $admission->getDischargeDate() != $kxEpisodeInfo->getDischargeTime()) {
                    // The ADMISSION was discharged, but the date has changed
                    $admission->setDischargeDate($kxEpisodeInfo->getDischargeTime());
                    $admissionModified = true;
                }
            }
            if (!$admission->getActiveReferralId() && $referral) {
                /*
                 * The Admission does not have a referral assigned, but we know the doctor assigned to the episode, so we can assign the referral
                 */
                $admission->setActiveReferralId($referral->getId());
                $admission->setActiveReferralTeamId($GLOBALS['CASE_MANAGERS_TEAM']);
                $admissionModified = true;
            }
            if ($admissionModified) {
                $admission->save();
            }

            $isNewFollowupAdmission = false;
            $followUpAdmission = null;
            if (!$kxEpisodeInfo->getDischargeTime() ||
                    ($GLOBALS['DISCHARGE_DATE_THRESHOLD'] && $kxEpisodeInfo->getDischargeTime() >= $GLOBALS['DISCHARGE_DATE_THRESHOLD'])) {
                // Create or update an associate ADMISSION in the "DISCHARGE_FOLLOWUP" PROGRAM
                if ($isNewEpisode) {
                    /*
                     * We received a new Episode from Kangxin, so it is necessary to create an associated ADMISSION in the "DISCHARGE_FOLLOWUP"
                     * PROGRAM
                     */
                    ServiceLogger::getInstance()->debug('Create new ADMISSION in DISCHARGE_FOLLOWUP care plan', 2);
                    $followUpAdmission = $this->createFollowupAdmission($patient, $kxEpisodeInfo, $episodeInfoForm, $dschFollowupSubscription,
                            $isNewFollowupAdmission);
                }
            }

            if (!$followUpAdmission) {
                // Find the Followup ADMISSION that was created for this episode (if any)
                if ($followUpAdmission = $this->findFollowupAdmission($episodeInfoForm, $patient, $dschFollowupSubscription)) {
                    ServiceLogger::getInstance()->debug("The related FOLLOWUP ADMISSION was found: " . $followUpAdmission->getId(), 2);
                } else {
                    ServiceLogger::getInstance()->debug("The related FOLLOWUP ADMISSION of this episode doesn't exist", 2);
                }
            }

            // If the new information has any change respect the previous infomation received, create an EVENT to inform the Case Manager
            if ($GLOBALS['INFORM_EPISODE_CHANGES'] && $followUpAdmission && $kxEpisodeInfo->hasChanges() &&
                    ($message = $kxEpisodeInfo->generateChangeMessage())) {
                // The EVENT will be created only the ADMISSION is not new and it is active
                $followUpAdmissionIsActive = !in_array($followUpAdmission->getStatus(),
                        [APIAdmission::STATUS_DISCHARGED, APIAdmission::STATUS_REJECTED]);
                if ($followUpAdmissionIsActive && !$isNewFollowupAdmission) {
                    ServiceLogger::getInstance()->debug('Send notification to the referral', 2);
                    $options = new stdClass();
                    $options->assign_to_role = APITaskAssignment::REFERRAL;
                    $this->apiLK->event_insert(currentDate($this->apiLK->getSession()->getTimezone()), $patient->getId(), null,
                            self::EPISODE_CHANGE_EVENT_CODE, null, $followUpAdmission->getId(), $message, $options);
                } elseif (!$followUpAdmissionIsActive) {
                    ServiceLogger::getInstance()->debug('Do not send notification to the referral because the FOLLOWUP ADMISSION is not active', 2);
                } elseif ($isNewFollowupAdmission) {
                    ServiceLogger::getInstance()->debug('Do not send notification to the referral because it is a new FOLLOWUP ADMISSION', 2);
                }
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
            $errMsg = 'ERROR CREATING/UPDATING ADMISSION FOR PATIENT ' . $kxEpisodeInfo->getName() . '(' . $kxEpisodeInfo->getIdCard() . '): ' .
                    $errMsg;
            throw new ServiceException($errCode, $errMsg);
        }
    }

    /**
     * ******************************** INTERNAL FUNCTIONS *********************************
     */

    /**
     * Creates a new patient (or updates it if already exists) in Linkcare database using as reference the information in $importInfo
     *
     * @param KangxinPatientInfo $importInfo
     * @param APISubscription $subscription
     * @return APICase
     */
    private function createPatient($importInfo, $subscription = null) {
        // Check if there already exists a patient with the Kangxin SickId
        $searchCondition = new StdClass();
        $searchCondition->identifier = new StdClass();
        $searchCondition->identifier->code = $GLOBALS['PATIENT_IDENTIFIER'];
        $searchCondition->identifier->value = $importInfo->getSickId();
        $searchCondition->identifier->team = $GLOBALS['HOSPITAL_TEAM'];
        $found = $this->apiLK->case_search(json_encode($searchCondition));
        if (!empty($found)) {
            $patientId = $found[0]->getId();
        }

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
            $nationalId = new APIIdentifier($identifierName, $importInfo->getIdCard());
            $contactInfo->addIdentifier($nationalId);
        }
        if ($importInfo->getSickId()) {
            // Add the internal ID of the patient in Kangxin Hospital as an IDENTIFIER object in Linkcare platform
            $sickId = new APIIdentifier($GLOBALS['PATIENT_IDENTIFIER'], $importInfo->getSickId());
            $sickId->setTeamId($GLOBALS['HOSPITAL_TEAM']);
            $contactInfo->addIdentifier($sickId);
        }

        if ($patientId) {
            $this->apiLK->case_set_contact($patientId, $contactInfo);
        } else {
            $patientId = $this->apiLK->case_insert($contactInfo, $subscription ? $subscription->getId() : null, true);
        }
        return $this->apiLK->case_get($patientId);
    }

    /**
     *
     * @param APIUser $employeeRef
     * @param string $name
     * @param string $roleId
     * @return APIUser
     */
    private function createProfessional($employeeRef, $name, $teamId, $roleId) {
        if (isNullOrEmpty($employeeRef)) {
            return null;
        }
        if (array_key_exists($employeeRef, $this->professionals)) {
            // Cached in memory. Not necessary to update or insert the professional
            return $this->professionals[$employeeRef];
        }
        // Check if there already exists a professional with the Kangxin employee Id
        $searchCondition = new StdClass();
        $searchCondition->identifier = new StdClass();
        $searchCondition->identifier->code = $GLOBALS['PROFESSIONAL_IDENTIFIER'];
        $searchCondition->identifier->value = $employeeRef;
        $searchCondition->identifier->team = $GLOBALS['HOSPITAL_TEAM'];
        $found = $this->apiLK->user_search(json_encode($searchCondition));
        if (!empty($found)) {
            $userId = $found[0]->getId();
        }

        $contactInfo = new APIContact();
        if ($name) {
            $contactInfo->setCompleteName($name);
        }

        // Add the internal ID of the professional in Kangxin Hospital as an IDENTIFIER object in Linkcare platform
        $employeeId = new APIIdentifier($GLOBALS['PROFESSIONAL_IDENTIFIER'], $employeeRef);
        $employeeId->setTeamId($GLOBALS['HOSPITAL_TEAM']);
        $contactInfo->addIdentifier($employeeId);

        if (!$userId) {
            $userId = $this->apiLK->team_user_insert($contactInfo, $teamId, $roleId);
        } else {
            $userId = $this->apiLK->team_member_add($contactInfo, $teamId, $userId, 'USER', $roleId);
        }

        $professional = $this->apiLK->user_get($userId);
        $this->professionals[$employeeRef] = $professional;
        return $professional;
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
                $item = $form->findQuestion(KangxinItemCodes::RESIDENCE_NO);
                if ($item->getValue() == $importInfo->getResidenceNo()) {
                    // The episode already exists
                    $foundEpisodeTask = $episodeTask;
                    break;
                }
            }
            if ($foundEpisodeTask) {
                break;
            }
        }

        if ($foundEpisodeTask) {
            // There already exists a Task for the Kangxin episode
            return $this->apiLK->admission_get($foundEpisodeTask->getAdmissionId());
        }
        return null;
    }

    /**
     * Creates a new Admission for a patient in the "Kangxin Episodes" PROGRAM
     *
     * @param APICase $case
     * @param KangxinPatientInfo $episodeInfo
     * @param APISubscription $subscription
     * @return APIAdmission
     */
    private function createEpisodeAdmission($case, $episodeInfo, $subscription) {
        $setupParameters = new stdClass();

        $setupParameters->{KangxinItemCodes::SICK_ID} = $episodeInfo->getSickId();
        $setupParameters->{KangxinItemCodes::SICK_NUM} = $episodeInfo->getSickNum();
        $setupParameters->{KangxinItemCodes::RESIDENCE_NO} = $episodeInfo->getResidenceNo();
        $setupParameters->{KangxinItemCodes::SOURCE} = 2;

        return $this->apiLK->admission_create($case->getId(), $subscription->getId(), $episodeInfo->getAdmissionTime(), null, true, $setupParameters);
    }

    /**
     * Creates a new Admission for a patient in the "Kangxin Followup" PROGRAM
     *
     * @param APICase $case
     * @param KangxinPatientInfo $episodeInfo
     * @param APIForm $episodeInfoForm
     * @param APISubscription $subscription
     * @param boolean &$isNew
     * @return APIAdmission
     */
    private function createFollowupAdmission($case, $episodeInfo, $episodeInfoForm, $subscription, &$isNew) {
        /*
         * First of all check whether it is really necessary to create a new ADMISSION.
         * If any ADMISSION exists with a enroll date posterior to the admission date received from Kangxin, then it is not necessary to create a new
         * one.
         */
        $existingAdmissions = $this->apiLK->case_admission_list($case->getId(), true, $subscription->getId());
        /** @var APIAdmission $found */
        $found = null;
        $isNew = true;
        if (!empty($existingAdmissions)) {
            // Sort descending by enroll date so that the most recent Admission is the first of the list
            usort($existingAdmissions,
                    function ($adm1, $adm2) {
                        /** @var APIAdmission $adm1 */
                        /** @var APIAdmission $adm2 */
                        return strcmp($adm2->getEnrolDate(), $adm1->getEnrolDate());
                    });
            $found = reset($existingAdmissions);
        }

        if ($found) {
            /*
             * If there already exists an ADMISSION, maybe it is not necessary to create a new one.
             * There are 2 situations where it is not necessary to create a new ADMISSION
             * 1) The last ADMISSION found is active
             * 2) The last ADMISSION found is not active, but it has an enroll date posterior to the admission date received from Kangxin. This
             * situation is strange, but it may mean that we are receiving an update of an old record
             */
            $isActive = !in_array($found->getStatus(), [APIAdmission::STATUS_DISCHARGED, APIAdmission::STATUS_REJECTED]);
            if ($isActive || $episodeInfo->getAdmissionTime() < $found->getEnrolDate()) {
                $isNew = false;
                $admission = $found;
            }
        }

        if (!$admission) {
            $admission = $this->apiLK->admission_create($case->getId(), $subscription->getId(), null, null, true);
        }

        if ($episodeInfoForm && $q = $episodeInfoForm->findQuestion(KangxinItemCodes::FOLLOWUP_ADMISSION)) {
            /*
             * Update the ID of the Admission created in the FORM where the rest of the information about the episode is stored.
             */
            $q->setAnswer($admission->getId());
            $this->apiLK->form_set_answer($episodeInfoForm->getId(), $q->getId(), $admission->getId());
        }

        return $admission;
    }

    /**
     * Finds the ADMISSION created in the Discharge Followup PROGRAM that is related to a Kangxin clinical episode.
     * The information about the related ADMISSION is stored in an ITEM of the FORM that holds all the information about the clinical episode
     *
     * @param APIForm $episodeInfoForm
     * @param APICase $case
     * @param APISubscription $subscription
     * @return APIAdmission
     */
    private function findFollowupAdmission($episodeInfoForm, $case, $subscription) {
        if (!$episodeInfoForm) {
            return null;
        }

        $q = $episodeInfoForm->findQuestion(KangxinItemCodes::FOLLOWUP_ADMISSION);
        $followupAdmissionId = $q ? $q->getAnswer() : null;

        if ($followupAdmissionId) {
            try {
                $followUpAdmission = $this->apiLK->admission_get($followupAdmissionId);
            } catch (APIException $e) {
                /*
                 * If cannot load the associated Follow-up ADMISSION, it may mean that it has been deleted, which shouldn't be a problem.
                 * If any other case, generate an error
                 */
                if ($e->getCode() != "ADMISSION.NOT_FOUND") {
                    throw $e;
                }
            }
            return $followUpAdmission;
        }

        /*
         * We don't have any Admission ID stored in the epsisode info FORM
         * Try to find an active ADMISSION
         */
        $existingAdmissions = $this->apiLK->case_admission_list($case->getId(), true, $subscription->getId());
        /** @var APIAdmission $found */
        foreach ($existingAdmissions as $admission) {
            if (!in_array($admission->getStatus(), [APIAdmission::STATUS_DISCHARGED, APIAdmission::STATUS_REJECTED])) {
                return $admission;
            }
        }

        return null;
    }

    /**
     * Updates the information related with a specific episode of the patient.
     * There exists a TASK with TASK_CODE = XXXXX for each episode.<br>
     * The return value is the APIForm with the information about the episode
     *
     * @param APIAdmission $admission
     * @param KangxinPatientInfo $kxEpisodeInfo
     * @param APIUser $referral
     * @return APIForm
     */
    private function updateEpisodeData($admission, $kxEpisodeInfo, $referral) {
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
            $episodeTask = $admission->insertTask(self::PATIENT_HISTORY_TASK_CODE, $kxEpisodeInfo->getAdmissionTime());
        }

        $episodeForms = $episodeTask->findForm(self::EPISODE_FORM_CODE);
        foreach ($episodeForms as $form) {
            $item = $form->findQuestion(KangxinItemCodes::RESIDENCE_NO);
            if (!$item->getValue()) {
                $emptyForm = $form;
            } elseif ($item->getValue() == $kxEpisodeInfo->getResidenceNo()) {
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
            $q->setAnswer($kxEpisodeInfo->getUpdateTime());
            $arrQuestions[] = $q;
        }

        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_ID)) {
            $q->setAnswer($kxEpisodeInfo->getSickId());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::SICK_NUM)) {
            $q->setAnswer($kxEpisodeInfo->getSickNum());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ETHNICITY)) {
            $q->setAnswer($kxEpisodeInfo->getEthnicity());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ETHNICITY_OPTIONS)) {
            // Select the option by its value
            $q->setValue($kxEpisodeInfo->getEthnicity());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ID_CARD)) {
            $q->setAnswer($kxEpisodeInfo->getIdCard());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ID_CARD_TYPE)) {
            $q->setAnswer($kxEpisodeInfo->getIdCardType());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PHONE)) {
            $q->setAnswer($kxEpisodeInfo->getPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::MARITAL)) {
            $q->setAnswer($kxEpisodeInfo->getMarital());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::MARITAL_OPTIONS)) {
            // Select the option by its value
            $q->setValue($kxEpisodeInfo->getMarital());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PATIENT_TYPE)) {
            $q->setAnswer($kxEpisodeInfo->getKind());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PROFESSION)) {
            $q->setAnswer($kxEpisodeInfo->getProfession());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::PROFESSION_OPTIONS)) {
            // Select the option by its value
            $q->setValue($kxEpisodeInfo->getProfession());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_NAME)) {
            $q->setAnswer($kxEpisodeInfo->getContactName());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATION)) {
            $q->setAnswer($kxEpisodeInfo->getRelation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATION_OPTIONS)) {
            // Select the option by its value
            $q->setValue($kxEpisodeInfo->getRelation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATIONSHIP_CODE)) {
            // Select the option by its value
            $q->setValue($kxEpisodeInfo->getRelation([$this, 'mapRelationshipCodeValue']));
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::RELATIVE_FAMILY_CODE)) {
            $q->setAnswer($kxEpisodeInfo->getRelation([$this, 'mapRelativeCodeValue']));
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::CONTACT_PHONE)) {
            $q->setAnswer($kxEpisodeInfo->getContactPhone());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::ADMISSION_DIAG)) {
            $q->setAnswer($kxEpisodeInfo->getAdmissionDiag());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITAL_ADMISSION)) {
            $q->setAnswer($kxEpisodeInfo->getHospitalAdmission());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_DIAG)) {
            $q->setAnswer($kxEpisodeInfo->getDischargeDiag());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS_CODE)) {
            $q->setAnswer($kxEpisodeInfo->getDischargeDiseaseCode());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
            $q->setAnswer($kxEpisodeInfo->getDischargeMainDiagnosis());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DRUG_ALLERGY)) {
            $q->setAnswer($kxEpisodeInfo->getDrugAllergy());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::HOSPITALIZED)) {
            $q->setAnswer($kxEpisodeInfo->getHospitalized());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_SITUATION)) {
            $q->setAnswer($kxEpisodeInfo->getDischargeSituation());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_INSTRUCTIONS)) {
            $q->setAnswer($kxEpisodeInfo->getDischargeInstructions());
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
                $q->setAnswer($kxEpisodeInfo->getResidenceNo());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::VISIT_NUMBER)) {
                $q->setAnswer($kxEpisodeInfo->getVisitNumber());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ADMISSION_TIME)) {
                $q->setAnswer($kxEpisodeInfo->getAdmissionTime());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::ADMISSION_DEPARTMENT)) {
                $q->setAnswer($kxEpisodeInfo->getAdmissionDept());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_TIME)) {
                $q->setAnswer($kxEpisodeInfo->getDischargeTime());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_DEPARTMENT)) {
                $q->setAnswer($kxEpisodeInfo->getDischargeDept());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_STATUS)) {
                $q->setAnswer($kxEpisodeInfo->getDischargeStatus());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_STATUS_OPTIONS)) {
                $q->setValue($kxEpisodeInfo->getDischargeStatus());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DOCTOR)) {
                $q->setAnswer($kxEpisodeInfo->getDoctor());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DOCTOR_CODE)) {
                $q->setAnswer($kxEpisodeInfo->getDoctorCode());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::RESPONSIBLE_NURSE)) {
                $q->setAnswer($kxEpisodeInfo->getResponsibleNurse());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::RESPONSIBLE_NURSE_CODE)) {
                $q->setAnswer($kxEpisodeInfo->getResponsibleNurseCode());
                $arrQuestions[] = $q;
            }
        }

        $ix = 1;
        $diagnosis = $kxEpisodeInfo->getDiagnosis();
        if (!empty($diagnosis) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::DIAGNOSIS_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS_CODE)) {
                $q->setAnswer($kxEpisodeInfo->getDischargeDiseaseCode());
                $arrQuestions[] = $q;
            }
            if ($q = $episodeInfoForm->findArrayQuestion($arrayHeader->getId(), $ix, KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
                $q->setAnswer($kxEpisodeInfo->getDischargeMainDiagnosis());
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
        $procedures = $kxEpisodeInfo->getProcedures();
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

        if ($referral) {
            // Assign the Task to the referral of the Admission (if not assigned yet)
            foreach ($episodeTask->getAssignments() as $assignment) {
                if ($assignment->getUserId() == $referral->getId() && ($assignment->getRoleId() == APIRole::CASE_MANAGER)) {
                    $alreadyAssigned = true;
                    break;
                }
            }

            if (!$alreadyAssigned) {
                $episodeTask->clearAssignments();
                $assignment = new APITaskAssignment(APIRole::CASE_MANAGER, $GLOBALS['CASE_MANAGERS_TEAM'], $referral->getId());
                $episodeTask->addAssignments($assignment);
            }
        }

        if ($kxEpisodeInfo->getAdmissionTime()) {
            $dateParts = explode(' ', $kxEpisodeInfo->getAdmissionTime());
            $date = $dateParts[0];
            $time = $dateParts[1];
            $episodeTask->setDate($date);
            $episodeTask->setHour($time);
            $episodeTask->setLocked(true);
        }

        $episodeTask->save();

        return $episodeInfoForm;
    }

    /**
     * Creates or updates a TASK for each operation (medical procedure) of the clinical episode
     *
     * @param APIAdmission $admission
     * @param KangxinPatientInfo $kxEpisodeInfo
     */
    private function updateOperationTasks($admission, $kxEpisodeInfo) {
        $filter = new TaskFilter();
        $filter->setTaskCodes(self::KANGXIN_OPERATION_TASK_CODE);
        $currentTaskList = $admission->getTaskList(1, 0, $filter);

        /*
         * If there already exist some TASKs, check which corresponds to each procedure so that we can update it.
         * If some procedure doesn't have a related TASK, then it will be necessary to create a new one
         */
        $existingOperationTasks = [];
        foreach ($currentTaskList as $task) {
            $operationForms = $task->findForm(self::KANGXIN_OPERATION_FORM_CODE);
            /** @var APIForm $form */
            $form = !empty($operationForms) ? reset($operationForms) : null;
            if ($form) {
                $item = $form->findQuestion(KangxinItemCodes::OPERATION_ID);
                $existingOperationTasks[$item->getValue()] = ['task' => $task, 'form' => $form];
            }
        }

        foreach ($kxEpisodeInfo->getProcedures() as $procedure) {
            if (!array_key_exists($procedure->getApplyOperatNo(), $existingOperationTasks)) {
                // It is necessary to create a new TASK to store the operation information
                $task = $admission->insertTask(self::KANGXIN_OPERATION_TASK_CODE);
                $operationForms = $task->findForm(self::KANGXIN_OPERATION_FORM_CODE);
                if (empty($operationForms)) {
                    $errorMsg = 'ERROR UPDATING EPISODE DATA OF PATIENT ' . $kxEpisodeInfo->getName() . '(' . $kxEpisodeInfo->getSickId() . '): ';
                    $errorMsg .= 'Cannot store operation data: The FORM with code: "' . self::KANGXIN_OPERATION_FORM_CODE .
                            '" does not exist in TASK "' . $task->getTaskCode() . '" (' . $task->getId() . ')';
                    throw new ServiceException(ErrorCodes::DATA_MISSING, $errorMsg);
                }
                $form = !empty($operationForms) ? reset($operationForms) : null;
                $existingOperationTasks[$procedure->getApplyOperatNo()] = ['task' => $task, 'form' => $form];
            } else {
                $task = $existingOperationTasks[$procedure->getApplyOperatNo()]['task'];
                $form = $existingOperationTasks[$procedure->getApplyOperatNo()]['form'];
            }

            $arrQuestions = [];
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_ID)) {
                $q->setAnswer($procedure->getApplyOperatNo());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::PROCESS_ORDER)) {
                $q->setAnswer($procedure->getProcessOrder());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_DATE)) {
                $q->setAnswer($procedure->getOperationDate());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_LEVEL)) {
                $q->setAnswer($procedure->getOperationLevel());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_DOCTOR)) {
                $q->setAnswer($procedure->getOperationDoctor());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_DOCTOR_CODE)) {
                $q->setAnswer($procedure->getOperationDoctorCode());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_TYPE)) {
                $q->setAnswer($procedure->getOperationType());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_CODE)) {
                $q->setAnswer($procedure->getOperationCode());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_NAME)) {
                $q->setAnswer($procedure->getOperationName());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_NAME_1)) {
                $q->setAnswer($procedure->getOperationName1());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_NAME_2)) {
                $q->setAnswer($procedure->getOperationName2());
                $arrQuestions[] = $q;
            }
            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_NAME_3)) {
                $q->setAnswer($procedure->getOperationName3());
                $arrQuestions[] = $q;
            }

            if ($q = $form->findQuestion(KangxinItemCodes::OPERATION_NAME_4)) {
                $q->setAnswer($procedure->getOperationName4());
                $arrQuestions[] = $q;
            }

            if (!empty($arrQuestions)) {
                $this->apiLK->form_set_all_answers($form->getId(), $arrQuestions, true);
            }

            // Update the datetime of the TAKS if necessary
            $dateParts = explode(' ', $procedure->getOperationDate());
            $date = $dateParts[0];
            $time = count($dateParts) > 1 ? $dateParts[1] : null;

            // Assign the Task to the operation doctor
            if ($procedure->getOperationDoctorCode()) {
                $operationDoctor = $this->createProfessional($procedure->getOperationDoctorCode(), $procedure->getOperationDoctorName(),
                        $GLOBALS['SURGEONS_TEAM'], APIRole::STAFF);
                $alreadyAssigned = false;
                foreach ($task->getAssignments() as $assignment) {
                    if ($assignment->getUserId() == $operationDoctor->getId() && ($assignment->getRoleId() == APIRole::STAFF)) {
                        $alreadyAssigned = true;
                        break;
                    }
                }
                if (!$alreadyAssigned) {
                    $task->clearAssignments();
                    $assignment = new APITaskAssignment(APIRole::STAFF, $GLOBALS['SURGEONS_TEAM'], $operationDoctor->getId());
                    $task->addAssignments($assignment);
                }
            }

            if (!$task->addAssignments($assignment))
                $task->setLocked(true);

            if ($task->getDate() != $date || (!$time && $task->getHour() != $time)) {
                $task->setDate($procedure->getOperationDate());
                $task->setHour($time);
            }
            $task->save();
        }
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
