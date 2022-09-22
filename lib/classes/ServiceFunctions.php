<?php

/**
 * ******************************** REST FUNCTIONS *********************************
 */
class ServiceFunctions {
    /** @var LinkcareSoapAPI */
    private $apiLK;
    /** @var KangxinAPI */
    private $apiKangxin;

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

        $errorMessages = [];

        if (empty($patientsToImport)) {
            return ['success' => 0, 'failed' => 0, 'errors' => $errorMessages];
        }

        try {
            $subscription = $this->apiLK->subscription_get($GLOBALS['PROGRAM_CODE'], $GLOBALS['TEAM_CODE']);
        } catch (Exception $e) {
            $errorMessages[] = 'ERROR LOADING SUBSCRIPTION FOR IMPORTING PATIENTS: ' . $e->getMessage();
            return ['success' => 0, 'failed' => 0, 'errors' => $errorMessages];
        }

        $importFailed = [];
        foreach ($patientsToImport as $importInfo) {
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
                $errorMessages = 'ERROR CREATING PATIENT ' . $importInfo->getName() . '(' . $importInfo->getIdCardNo() . '): ' . $errMsg;
                $importFailed[] = $importInfo;
                continue;
            }

            // Create a new Admission for the patient
            try {
                $this->createAdmission($patientId, $importInfo, $subscription);
            } catch (ServiceException $se) {
                $errMsg = 'Import service generated an exception: ' . $se->getMessage();
            } catch (APIException $ae) {
                $errMsg = 'Linkcare API returned an exception: ' . $ae->getMessage();
            } catch (Exception $e) {
                $errMsg = 'Unexpected exception: ' . $e->getMessage();
            }
            if ($errMsg) {
                $errorMessages = 'ERROR CREATING ADMISSION FOR PATIENT ' . $importInfo->getName() . '(' . $importInfo->getIdCardNo() . '): ' . $errMsg;
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

        if ($importInfo->getIdCardNo()) {
            $searchCondition = new StdClass();
            $searchCondition->identifier = new StdClass();
            $searchCondition->identifier->code = $GLOBALS['PATIENT_IDENTIFIER'];
            $searchCondition->identifier->value = $importInfo->getIdCardNo();
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

        if ($importInfo->getFamilyPhone()) {
            $phone = new APIContactChannel();
            $phone->setValue($importInfo->getFamilyPhone());
            $phone->setCategory('mobile');
            $contactInfo->addPhone($phone);
        }

        if ($importInfo->getIdCardNo()) {
            $nationalId = new APIIdentifier($GLOBALS['NATIONAL_IDENTIFIER'], $importInfo->getIdCardNo());
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
     * @return string
     */
    private function createAdmission($caseId, $importInfo, $subscription) {
        $setupParameters = new stdClass();
        $setupParameters->PATIENT_ID = $importInfo->getSickId();
        $setupParameters->RESIDENCE_NO = $importInfo->getResidenceNo();
        $setupParameters->ENROL_AGE = $importInfo->getAge();
        $setupParameters->ASSOCIATE_NAME = $importInfo->getAssociationName();
        $setupParameters->ASSOCIATE_RELATION = $importInfo->getSocietyRelation();
        $setupParameters->ASSOCIATE_PHONE = $importInfo->getAssociationPhone();
        $setupParameters->ADMISSION_TIME = $importInfo->getAdmissionTime();
        $setupParameters->ADMISSION_DEPARTMENT = $importInfo->getAdmissionDepartment();
        $setupParameters->ADMISSION_WARD = $importInfo->getAdmissionWard();
        $setupParameters->DISCHARGE_TIME = $importInfo->getDischargeTime();
        $setupParameters->DISCHARGE_DEPARTMENT = $importInfo->getDischargeDepartment();
        $setupParameters->DISCHARGE_WARD = $importInfo->getDischargeWard();
        $setupParameters->ACTUAL_HOSPITAL_DAYS = $importInfo->getActualHospitalDays();
        $setupParameters->DISCHARGE_DISEASE_CODE = $importInfo->getDischargeDiseaseCode();
        $setupParameters->DISCHARGE_MAIN_DIAG = $importInfo->getDischargeMainDiagnosis();
        $setupParameters->OTHER_DISEASE_CODES = $importInfo->getOtherDiseaseCodes();
        $setupParameters->DISCHARGE_OTHER_DIAG = $importInfo->getDischargeOtherDiagnoses();
        $setupParameters->OPERATION_CODE = $importInfo->getOperationCode();
        $setupParameters->OPERATION_NAME = $importInfo->getOperationName();
        $setupParameters->OPERATION_DATE = $importInfo->getOperationDate();
        $setupParameters->OPERATION_LEVEL = $importInfo->getOperationLevel();
        $setupParameters->OPERATION_SURGEON = $importInfo->getOperationSurgeon();
        $setupParameters->DRUG_ALLERGY = $importInfo->getDrugAllergy();
        $setupParameters->DOCTOR = $importInfo->getDoctor();
        $setupParameters->RESPONSIBLE_NURSE = $importInfo->getResponsibleNurse();
        $setupParameters->HOSPITAL_ADMISSION = $importInfo->getHospitalAdmission();
        $setupParameters->HOSPITALIZED = $importInfo->getHospitalized();
        $setupParameters->DISCHARGE_SITUATION = $importInfo->getDischargeSituation();
        $setupParameters->DISCHARGE_INSTRUCT = $importInfo->getDischargeInstructions();

        return $this->apiLK->admission_create($caseId, $subscription->getId(), null, null, true, $setupParameters);
    }
}
