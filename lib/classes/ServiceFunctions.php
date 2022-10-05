<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */
class ServiceFunctions {
    /** @var LinkcareSoapAPI */
    private $apiLK;
    /** @var KangxinAPI */
    private $apiKangxin;
    const PATIENT_HISTORY_TASK_CODE = 'PCI_DCH_KANGXIN_IMPORT';
    const EPISODE_FORM_CODE = 'PCI_DCH_KANGXIN_IMPORT_FORM';
    const EPISODE_ID_ITEM_CODE = 'RESIDENCE_NO';

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
     * Sends a request to Kangxin Hospital to retrieve the list of patients that should be imported in the Linkcare Platform
     * The return value is an associative array with the following items:
     * <ul>
     * <li>success: Number of patients successfully imported</li>
     * <li>failed: Number of patients not imported due to an error</li>
     * <li>errors: An array of error messages</li>
     * </ul>
     *
     * @return string[]
     */
    function importPatients() {
        $patientsToImport = $this->apiKangxin->requestPatientList($GLOBALS['PATIENT_BATCH_SIZE'], 1);

        ServiceLogger::getInstance()->debug(
                'Patients requested to Kangxin: ' . $GLOBALS['PATIENT_BATCH_SIZE'] . ', received: ' . count($patientsToImport));

        $errorMessages = [];

        if (empty($patientsToImport)) {
            return ['success' => 0, 'failed' => 0, 'errors' => $errorMessages];
        }

        try {
            $subscription = $this->apiLK->subscription_get($GLOBALS['PROGRAM_CODE'], $GLOBALS['TEAM_CODE']);
        } catch (Exception $e) {
            $errorMessages[] = 'ERROR LOADING SUBSCRIPTION (Program: ' . $GLOBALS['PROGRAM_CODE'] . ', Team: ' . $GLOBALS['TEAM_CODE'] .
                    ') FOR IMPORTING PATIENTS: ' . $e->getMessage();
            return ['success' => 0, 'failed' => 0, 'errors' => $errorMessages];
        }

        $importFailed = [];
        $success = [];
        foreach ($patientsToImport as $ix => $importInfo) {
            ServiceLogger::getInstance()->debug(
                    'Importing patient ' . sprintf('%03d', $ix) . ': ' . $importInfo->getName() . ' (SickId: ' . $importInfo->getSickId() .
                    ', Id. card: ' . $importInfo->getIdentityNumber() . ', Episode: ' . $importInfo->getResidenceNo() . ')', 1);
            $errMsg = '';

            // Create or update the Patient in Linkcare platform
            try {
                $patientId = $this->createPatient($importInfo, $subscription);
            } catch (ServiceException $se) {
                $errMsg = 'Import service generated an exception: ' . $se->getMessage();
            } catch (APIException $ae) {
                $errMsg = 'Linkcare API returned an exception: ' . $ae->getMessage();
            } catch (Exception $e) {
                $errMsg = 'Unexpected exception: ' . $e->getMessage();
            }
            if ($errMsg) {
                $errorMessages[] = 'ERROR CREATING PATIENT ' . $importInfo->getName() . '(' . $importInfo->getIdentityNumber() . '): ' . $errMsg;
                $importFailed[] = $importInfo;
                continue;
            }

            // Create a new Admission for the patient or find the existing one
            try {
                $admission = $this->createAdmission($patientId, $importInfo, $subscription);
                $this->updateEpisodeData($admission, $importInfo);
            } catch (ServiceException $se) {
                $errMsg = 'Import service generated an exception: ' . $se->getMessage();
            } catch (APIException $ae) {
                $errMsg = 'Linkcare API returned an exception: ' . $ae->getMessage();
            } catch (Exception $e) {
                $errMsg = 'Unexpected exception: ' . $e->getMessage();
            }
            if ($errMsg) {
                $errorMessages[] = 'ERROR CREATING ADMISSION FOR PATIENT ' . $importInfo->getName() . '(' . $importInfo->getIdentityNumber() . '): ' .
                        $errMsg;
                $importFailed[] = $importInfo;
            } else {
                $success[] = $importInfo;
            }
        }

        return ['success' => count($success), 'failed' => count($importFailed), 'errors' => $errorMessages];
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
     * @return string
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
        return $this->apiLK->case_insert($contactInfo, $subscription ? $subscription->getId() : null, true);
    }

    /**
     * Creates a new Admission for a patient
     *
     * @param string $caseId
     * @param KangxinPatientInfo $importInfo
     * @param APISubscription $subscription
     * @return APIAdmission
     */
    private function createAdmission($caseId, $importInfo, $subscription) {
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
        $setupParameters->{KangxinItemCodes::OTHER_DISEASE_CODES} = $importInfo->getOtherDiseaseCodes();
        $setupParameters->{KangxinItemCodes::DISCHARGE_OTHER_DIAGNOSES} = $importInfo->getDischargeOtherDiagnoses();
        $setupParameters->{KangxinItemCodes::DRUG_ALLERGY} = $importInfo->getDrugAllergy();
        $setupParameters->{KangxinItemCodes::DOCTOR} = $importInfo->getDoctor();
        $setupParameters->{KangxinItemCodes::RESPONSIBLE_NURSE} = $importInfo->getResponsibleNurse();
        $setupParameters->{KangxinItemCodes::HNTH_HOSPITAL} = $importInfo->getNthHospital();
        $setupParameters->{KangxinItemCodes::HOSPITALIZED} = $importInfo->getHospitalized();
        $setupParameters->{KangxinItemCodes::DISCHARGE_SITUATION} = $importInfo->getDischargeSituation();
        $setupParameters->{KangxinItemCodes::DISCHARGE_INSTRUCTIONS} = $importInfo->getDischargeInstructions();
        $setupParameters->{KangxinItemCodes::NOTES} = $importInfo->getNote();
        $setupParameters->{KangxinItemCodes::SOURCE} = 2;

        return $this->apiLK->admission_create($caseId, $subscription->getId(), null, null, true, $setupParameters);
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
            $episodeTask = $admission->insertTask(self::PATIENT_HISTORY_TASK_CODE);
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
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_DISEASE_CODE)) {
            $q->setValue($importInfo->getDischargeDiseaseCode());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_MAIN_DIAGNOSIS)) {
            $q->setValue($importInfo->getDischargeMainDiagnosis());
            $arrQuestions[] = $q;
        }

        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::OTHER_DISEASE_CODES)) {
            $q->setValue($importInfo->getOtherDiseaseCodes());
            $arrQuestions[] = $q;
        }
        if ($q = $episodeInfoForm->findQuestion(KangxinItemCodes::DISCHARGE_OTHER_DIAGNOSES)) {
            $q->setValue($importInfo->getDischargeOtherDiagnoses());
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
        $procedures = $importInfo->getProcedures();
        if (!empty($procedures) && ($arrayHeader = $episodeInfoForm->findQuestion(KangxinItemCodes::PROCEDURE_ARRAY)) &&
                $arrayHeader->getType() == APIQuestion::TYPE_ARRAY) {
            foreach ($procedures as $procedure) {
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
    }
}
