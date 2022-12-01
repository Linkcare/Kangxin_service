<?php

class KangxinPatientInfo {
    /** @var stdClass */
    private $originalObject;
    /**
     * This member indicates if any change in the properties of the object must be tracked
     *
     * @var boolean
     */
    private $trackChanges = true;
    private $changeList = [];
    /**
     * When change tracking is set, whenever a new Procedure is added to the list of procedures it will also be tracked to know that the list of
     * procedures has been modified
     *
     * @var KangxinProcedure
     */
    private $newProcedures = [];

    /** @var string*/
    private $sickId;
    /** @var string*/
    private $sickNum;
    /** @var string*/
    private $name;
    /** @var string*/
    private $sex;
    /** @var string*/
    private $birthDate;
    /** @var string*/
    private $ethnicity;
    /** @var string*/
    private $nationality;
    /** @var string*/
    private $idCard;
    /** @var string*/
    private $idCardType;
    /** @var string*/
    private $phone;
    /** @var string*/
    private $contactName;
    /** @var string*/
    private $contactPhone;
    /** @var string*/
    private $relation;
    /** @var string*/
    private $marital;
    /** @var string*/
    private $kind;
    /** @var string*/
    private $profession;
    /** @var string*/
    private $drugAllergy;
    /** @var string*/
    private $residenceNo;
    /** @var string*/
    private $visitNumber;
    /** @var string*/
    private $admissionTime;
    /** @var string*/
    private $admissionDept;
    /** @var string*/
    private $dischargeTime;
    /** @var string*/
    private $dischargeDept;
    /** @var string*/
    private $doctor;
    /** @var string*/
    private $responsibleNurse;
    /** @var string*/
    private $dischargeStatus;
    /** @var string*/
    private $hospitalAdmission;
    /** @var string*/
    private $dischargeSituation;
    /** @var string*/
    private $dischargeInstructions;
    /** @var string*/
    private $hospitalized;
    /** @var string*/
    private $applyOperatNo;
    /** @var string*/
    private $operationDate;
    /** @var string*/
    private $processOrder;
    /** @var string*/
    private $updateTime;

    // Information regarding diagnosis
    /** @var string*/
    private $admissionDiag;
    /** @var string*/
    private $dischargeDiag;
    /** @var string*/
    private $dischargeDiseaseCode;
    /** @var string*/
    private $dischargeMainDiagnosis;
    /** @var string*/
    private $otherDiseaseCodes;
    /** @var string*/
    private $dischargeOtherDiagnoses;

    /** @var KangxinProcedure[] */
    private $procedures = [];
    /** @var KangxinDiagnosis[] */
    private $diagnosis = [];

    /**
     * ******* GETTERS *******
     */
    /**
     * Returns the string representation of the object as it was received
     *
     * @return stdClass
     */
    public function getOriginalObject() {
        return $this->originalObject;
    }

    /**
     * Internal Patient ID in Kangxin hospital
     *
     * @return string
     */
    public function getSickId() {
        return $this->sickId;
    }

    /**
     * Medical record number
     *
     * @return string
     */
    public function getSickNum() {
        return $this->sickNum;
    }

    /**
     * Inpatient episode ID
     *
     * @return string
     */
    public function getResidenceNo() {
        return $this->residenceNo;
    }

    /**
     * Patient Name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Patient gender
     *
     * @param callable $mapValueFn Function to map the internal value to another value
     * @return string
     */
    public function getSex($mapValueFn = null) {
        if (!$mapValueFn) {
            return $this->sex;
        }
        return $mapValueFn($this->sex);
    }

    /**
     * Patient date of birth
     *
     * @return string
     */
    public function getBirthDate() {
        return $this->birthDate;
    }

    /**
     * Patient Ethnicity
     *
     * @param callable $mapValueFn Function to map the internal value to another value
     * @return string
     */
    public function getEthnicity($mapValueFn = null) {
        if (!$mapValueFn) {
            return $this->ethnicity;
        }
        return $mapValueFn($this->ethnicity);
    }

    /**
     * Patient Nationality
     *
     * @return string
     */
    public function getNationality() {
        return $this->nationality;
    }

    /**
     * Patient identifier
     *
     * @return string
     */
    public function getIdCard() {
        return $this->idCard;
    }

    /**
     * Patient identifier type (national ID, passport...)
     *
     * @return string
     */
    public function getIdCardType() {
        return $this->idCardType;
    }

    /**
     * Mobile phone
     *
     * @return string
     */
    public function getPhone() {
        if (!isNullOrEmpty($this->phone) && !startsWith('+', $this->phone)) {
            return '+86' . $this->phone;
        }
        return $this->phone;
    }

    /**
     * Contact (associate) name
     *
     * @return string
     */
    public function getContactName() {
        return $this->contactName;
    }

    /**
     * Contact (associate) Relationship
     *
     * @param callable $mapValueFn Function to map the internal value to another value
     * @return string
     */
    public function getRelation($mapValueFn = null) {
        if (!$mapValueFn) {
            return $this->relation;
        }
        return $mapValueFn($this->relation);
    }

    /**
     * Marital status
     *
     * @param callable $mapValueFn Function to map the internal value to another value
     * @return string
     */
    public function getMarital($mapValueFn = null) {
        if (!$mapValueFn) {
            return $this->marital;
        }
        return $mapValueFn($this->marital);
    }

    /**
     * Patient type
     *
     * @return string
     */
    public function getKind() {
        return $this->kind;
    }

    /**
     * Patient profession
     *
     * @param callable $mapValueFn Function to map the internal value to another value
     * @return string
     */
    public function getProfession($mapValueFn = null) {
        if (!$mapValueFn) {
            return $this->profession;
        }
        return $mapValueFn($this->profession);
    }

    /**
     * Contact (associate) phone
     *
     * @return string
     */
    public function getContactPhone() {
        if (!isNullOrEmpty($this->contactPhone) && !startsWith('+', $this->contactPhone)) {
            return '+86' . $this->contactPhone;
        }
        return $this->contactPhone;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionTime() {
        return $this->admissionTime;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionDept() {
        return $this->admissionDept;
    }

    /**
     *
     * @return string
     */
    public function getHospitalAdmission() {
        return $this->hospitalAdmission;
    }

    /**
     *
     * @return string
     */
    public function getDischargeTime() {
        return $this->dischargeTime;
    }

    /**
     *
     * @return string
     */
    public function getDischargeDept() {
        return $this->dischargeDept;
    }

    /**
     *
     * @return string
     */
    public function getDischargeStatus() {
        return $this->dischargeStatus;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionDiag() {
        return $this->admissionDiag;
    }

    /**
     *
     * @return string
     */
    public function getDischargeDiag() {
        return $this->dischargeDiag;
    }

    /**
     * Main diagnosis code
     *
     * @return string
     */
    public function getDischargeDiseaseCode() {
        return $this->dischargeDiseaseCode;
    }

    /**
     * Main diagnosis name
     *
     * @return string
     */
    public function getDischargeMainDiagnosis() {
        return $this->dischargeMainDiagnosis;
    }

    /**
     *
     * @return string
     */
    public function getOtherDiseaseCodes() {
        return $this->otherDiseaseCodes;
    }

    /**
     *
     * @return string
     */
    public function getDischargeOtherDiagnoses() {
        return $this->dischargeOtherDiagnoses;
    }

    /**
     *
     * @return string
     */
    public function getDrugAllergy() {
        return $this->drugAllergy;
    }

    /**
     *
     * @return string
     */
    public function getDoctor() {
        return $this->doctor;
    }

    /**
     *
     * @return string
     */
    public function getDoctorName() {
        if (isNullOrEmpty($this->doctor)) {
            return null;
        }
        $parts = explode('/', $this->doctor);
        return $parts[0];
    }

    /**
     *
     * @return string
     */
    public function getDoctorCode() {
        if (isNullOrEmpty($this->doctor)) {
            return null;
        }
        $parts = explode('/', $this->doctor);
        if (count($parts) > 1) {
            return $parts[1];
        }

        return null;
    }

    /**
     *
     * @return string
     */
    public function getResponsibleNurse() {
        return $this->responsibleNurse;
    }

    /**
     *
     * @return string
     */
    public function getResponsibleNurseCode() {
        $parts = explode('/', $this->responsibleNurse);
        if (count($parts) > 1) {
            return $parts[1];
        }

        return null;
    }

    /**
     * Number of admissions
     *
     * @return string
     */
    public function getVisitNumber() {
        return $this->visitNumber;
    }

    /**
     *
     * @return string
     */
    public function getHospitalized() {
        return $this->hospitalized;
    }

    /**
     * Identifier of the operation.
     *
     * @return string
     */
    public function getApplyOperatNo() {
        return $this->applyOperatNo;
    }

    /**
     * Date of the operation
     *
     * @return string
     */
    public function getOperationDate() {
        return $this->operationDate;
    }

    /**
     *
     * @return string
     */
    public function getDischargeSituation() {
        return $this->dischargeSituation;
    }

    /**
     *
     * @return string
     */
    public function getDischargeInstructions() {
        return $this->dischargeInstructions;
    }

    /**
     *
     * @return string
     */
    public function getUpdateTime() {
        return $this->updateTime;
    }

    /**
     *
     * @return KangxinProcedure[]
     */
    public function getProcedures() {
        return $this->procedures;
    }

    /**
     *
     * @return KangxinDiagnosis[]
     */
    public function getDiagnosis() {
        return $this->diagnosis;
    }

    /**
     * ******* SETTERS *******
     */
    /**
     * Internal Patient ID in Kangxin hospital
     *
     * @param string $value
     */
    public function setSickId($value) {
        $this->sickId = $value;
    }

    /**
     * Medical record number
     *
     * @param string $value
     */
    public function setSickNum($value) {
        $this->sickNum = $value;
    }

    /**
     * Inpatient episode ID
     *
     * @param string $value
     */
    public function setResidenceNo($value) {
        $this->residenceNo = $value;
    }

    /**
     * Patient Name
     *
     * @param string $value
     */
    public function setName($value) {
        $this->trackPropertyChange('name', $value, $this->name);
        $this->name = $value;
    }

    /**
     * Patient gender
     *
     * @param string $value
     */
    public function setSex($value) {
        $this->trackPropertyChange('sex', $value, $this->sex);
        $this->sex = $value;
    }

    /**
     * Patient date of birth
     *
     * @param string $value
     */
    public function setBirthDate($value) {
        $this->trackPropertyChange('birthDate', $value, $this->birthDate);
        $this->birthDate = $value;
    }

    /**
     * Patient Ethnicity
     *
     * @param string $value
     */
    public function setEthnicity($value) {
        $this->trackPropertyChange('ethnicity', $value, $this->ethnicity);
        $this->ethnicity = $value;
    }

    /**
     * Patient Country
     *
     * @param string $value
     */
    public function setNationality($value) {
        $this->trackPropertyChange('nationality', $value, $this->nationality);
        $this->nationality = $value;
    }

    /**
     * Patient identifier
     *
     * @param string $value
     */
    public function setIdCard($value) {
        $this->trackPropertyChange('idCard', $value, $this->idCard);
        $this->idCard = $value;
    }

    /**
     * Patient identifier type (national ID, passport...)
     *
     * @param string $value
     */
    public function setIdCardType($value) {
        $this->trackPropertyChange('idCardType', $value, $this->idCardType);
        $this->idCardType = $value;
    }

    /**
     * Mobile phone
     *
     * @param string $value
     */
    public function setPhone($value) {
        $this->trackPropertyChange('phone', $value, $this->phone);
        $this->phone = $value;
    }

    /**
     * Contact (associate) name
     *
     * @param string $value
     */
    public function setContactName($value) {
        $this->trackPropertyChange('contactName', $value, $this->contactName);
        $this->contactName = $value;
    }

    /**
     * Contact (associate) Relationship
     *
     * @param string $value
     */
    public function setRelation($value) {
        $this->trackPropertyChange('relation', $value, $this->relation);
        $this->relation = $value;
    }

    /**
     * Marital status
     *
     * @param string $value
     */
    public function setMarital($value) {
        $this->trackPropertyChange('marital', $value, $this->marital);
        $this->marital = $value;
    }

    /**
     * Patient type
     *
     * @param string $value
     */
    public function setKind($value) {
        $this->trackPropertyChange('kind', $value, $this->kind);
        $this->kind = $value;
    }

    /**
     * Patient profession
     *
     * @param string $value
     */
    public function setProfession($value) {
        $this->trackPropertyChange('profession', $value, $this->profession);
        $this->profession = $value;
    }

    /**
     * Contact (associate) phone
     *
     * @param string $value
     */
    public function setContactPhone($value) {
        $this->trackPropertyChange('contactPhone', $value, $this->contactPhone);
        $this->contactPhone = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionTime($value) {
        $this->trackPropertyChange('admissionTime', $value, $this->admissionTime);
        $this->admissionTime = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionDept($value) {
        $this->trackPropertyChange('admissionDept', $value, $this->admissionDept);
        $this->admissionDept = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setHospitalAdmission($value) {
        $this->trackPropertyChange('hospitalAdmission', $value, $this->hospitalAdmission);
        $this->hospitalAdmission = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeTime($value) {
        $this->trackPropertyChange('dischargeTime', $value, $this->dischargeTime);
        $this->dischargeTime = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeDept($value) {
        $this->trackPropertyChange('dischargeDept', $value, $this->dischargeDept);
        $this->dischargeDept = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeStatus($value) {
        $this->trackPropertyChange('dischargeStatus', $value, $this->dischargeStatus);
        $this->dischargeStatus = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionDiag($value) {
        $this->trackPropertyChange('admissionDiag', $value, $this->admissionDiag);
        $this->admissionDiag = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeDiag($value) {
        $this->trackPropertyChange('dischargeDiag', $value, $this->dischargeDiag);
        $this->dischargeDiag = $value;
    }

    /**
     * Main diagnosis code
     *
     * @param string $value
     */
    public function setDischargeDiseaseCode($value) {
        $this->trackPropertyChange('dischargeDiseaseCode', $value, $this->dischargeDiseaseCode);
        $this->dischargeDiseaseCode = $value;
    }

    /**
     * Main diagnosis name
     *
     * @param string $value
     */
    public function setDischargeMainDiagnosis($value) {
        $this->trackPropertyChange('dischargeMainDiagnosis', $value, $this->dischargeMainDiagnosis);
        $this->dischargeMainDiagnosis = $value;
    }

    /**
     * Other diagnosis codes
     *
     * @param string $value
     */
    public function setOtherDiseaseCodes($value) {
        $this->trackPropertyChange('otherDiseaseCodes', $value, $this->otherDiseaseCodes);
        $this->otherDiseaseCodes = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $diagnosis = $this->findOrCreateDiagnosis($ix);
            $diagnosis->setCode($v);
        }
    }

    /**
     * Other diagnosis names
     *
     * @param string $value
     */
    public function setDischargeOtherDiagnoses($value) {
        $this->trackPropertyChange('dischargeOtherDiagnoses', $value, $this->dischargeOtherDiagnoses);
        $this->dischargeOtherDiagnoses = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $diagnosis = $this->findOrCreateDiagnosis($ix);
            $diagnosis->setName($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setDrugAllergy($value) {
        $this->trackPropertyChange('drugAllergy', $value, $this->drugAllergy);
        $this->drugAllergy = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDoctor($value) {
        $this->trackPropertyChange('doctor', $value, $this->doctor);
        $this->doctor = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setResponsibleNurse($value) {
        $this->trackPropertyChange('responsibleNurse', $value, $this->responsibleNurse);
        $this->responsibleNurse = $value;
    }

    /**
     * Number of admissions
     *
     * @param string $value
     */
    public function setVisitNumber($value) {
        $this->visitNumber = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setHospitalized($value) {
        $this->trackPropertyChange('hospitalized', $value, $this->hospitalized);
        $this->hospitalized = $value;
    }

    /**
     * Id of the operation
     *
     * @param string $value
     */
    public function setApplyOperatno($value) {
        $this->trackPropertyChange('applyOperatNo', $value, $this->applyOperatNo);
        $this->applyOperatNo = $value;
    }

    /**
     * Operation date
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->trackPropertyChange('operationDate', $value, $this->operationDate);
        $this->operationDate = $value;
    }

    /**
     * Procedure Order Name
     *
     * @param string $value
     */
    public function setProcessOrder($value) {
        $this->trackPropertyChange('processOrder', $value, $this->processOrder);
        $this->processOrder = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeSituation($value) {
        $this->trackPropertyChange('dischargeSituation', $value, $this->dischargeSituation);
        $this->dischargeSituation = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeInstructions($value) {
        $this->trackPropertyChange('dischargeInstructions', $value, $this->dischargeInstructions);
        $this->dischargeInstructions = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setUpdateTime($value) {
        $this->updateTime = $value;
    }

    /**
     * ******* METHODS *******
     */

    /**
     * Extracts the information about an operation from a record received from the Kangxin hospital.
     * A new KangxinOperation object will be added to the list of operations of the episode
     *
     * @param stdClass $info
     */
    public function addOperation($info) {
        if (isNullOrEmpty($info->applyOperatNo)) {
            return;
        }
        if (array_key_exists($info->applyOperatNo, $this->procedures)) {
            $procedure = $this->procedures[$info->applyOperatNo];
            $procedure->update($info);
        } else {
            $procedure = KangxinProcedure::fromJson($info);
            $this->procedures[$info->applyOperatNo] = $procedure;
            if ($this->trackChanges) {
                // A new procedure has been created and change trackin is active, so we store a list of new procedures added
                $this->newProcedures[$info->applyOperatNo] = $procedure;
            }
        }
    }

    /**
     * Updates a KangxinPatientInfo object from the information received from the Kangxin hospital
     *
     * @param stdClass $episodeOperations
     */
    public function update($episodeOperations) {
        if (empty($episodeOperations)) {
            return;
        }
        if (!is_array($episodeOperations)) {
            $episodeOperations = [$episodeOperations];
        }

        usort($episodeOperations,
                function ($op1, $op2) {
                    /* @var stdClass $op1 */
                    /* @var stdClass $op2 */
                    if ($op1->operationDate > $op2->operationDate) {
                        return -1;
                    } elseif ($op1->operationDate < $op2->operationDate) {
                        return 1;
                    }
                    return 0;
                });
        // The first operation of the list is the most recent one, so it contains the most updated general information about the patient and the
        // episode
        /* @var RecordPool $lastOperation */
        $lastOperation = reset($episodeOperations);

        $this->originalObject = $lastOperation;
        $jsonVars = get_object_vars($lastOperation);
        foreach ($jsonVars as $name => $value) {
            $setterFn = 'set' . strtoupper(substr($name, 0, 1)) . substr($name, 1);
            // The properties returned by the Kangxin service match the names of the members of this class
            if (method_exists($this, $setterFn)) {
                $this->{$setterFn}($value);
            }
        }

        // Now create the list of operations of this episode
        foreach ($episodeOperations as $operation) {
            $this->addOperation($operation);
        }
    }

    /**
     * Returns true if any property of the object has been modified
     *
     * @return boolean
     */
    public function hasChanges() {
        if (count($this->changeList) > 0 || count($this->newProcedures) > 0) {
            return true;
        }
        foreach ($this->getProcedures() as $proc) {
            if ($proc->hasChanges()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns a message composed by OBJECT CODES informing about the relevant changes detected.
     * Object Codes allow to handle language localization because the literals are defined in the PROGRAM
     *
     * @return string
     */
    function generateChangeMessage() {
        $itemCodes = [];
        if (!$this->hasChanges()) {
            return null;
        }

        // Check changes in operations
        if (count($this->newProcedures) > 0) {
            // New procedures added
            $itemCodes[] = 'OPERATION_NEW';
        } else {
            foreach ($this->getProcedures() as $proc) {
                if ($proc->hasChanges()) {
                    $itemCodes[] = 'OPERATION_UPDATE';
                    break;
                }
            }
        }

        // Check changes in discharge information
        if ($this->dischargeTime && array_key_exists('dischargeTime', $this->changeList) && isNullOrEmpty($this->changeList['dischargeTime'])) {
            // Discharge information has been added
            $itemCodes[] = 'DISCHARGE_NEW';
        } else {
            $fieldsToCheck = ['dischargeDept', 'dischargeDiag', 'dischargeInstructions', 'dischargeSituation', 'dischargeStatus', 'dischargeTime'];
            foreach ($fieldsToCheck as $fName) {
                if (array_key_exists($fName, $this->changeList)) {
                    $itemCodes[] = 'DISCHARGE_UPDATE';
                    break;
                }
            }
        }

        foreach ($itemCodes as $itemCode) {
            $messages[] = "@TASK{PCI_DCH_LITERALS}.FORM{PCI_DCH_EPISODE_UPDATE_MSGS}.ITEM{" . $itemCode . "}.TITLE";
        }

        return implode("\n", $messages);
    }

    /**
     * Creates a KangxinPatientInfo object from the information received from the Kangxin hospital
     *
     * @param stdClass $episodeOperations
     * @return KangxinPatientInfo
     */
    static function fromJson($episodeOperations) {
        $patientInfo = new KangxinPatientInfo();
        if (empty($episodeOperations)) {
            return $patientInfo;
        }
        if (!is_array($episodeOperations)) {
            $episodeOperations = [$episodeOperations];
        }

        /* This is the first time that we create the object, so it is not necessary to track the changes */
        $patientInfo->trackChanges = false;
        $patientInfo->update($episodeOperations);
        /* From this moment we want to track the changes in any of the object properties */
        $patientInfo->trackChanges = true;

        return $patientInfo;
    }

    /**
     * When the value of a property is changed, this function stores a copy of the previous value
     *
     * @param string $propertyName
     * @param string $newValue
     * @param string $previousValue
     */
    private function trackPropertyChange($propertyName, $newValue, $previousValue) {
        if (!$this->trackChanges) {
            return;
        }
        if (isNullOrEmpty($newValue)) {
            $newValue = null;
        }
        if (isNullOrEmpty($previousValue)) {
            $previousValue = null;
        }
        if ($newValue !== $previousValue) {
            $this->changeList[$propertyName] = $previousValue;
        }
    }

    /**
     *
     * @param int $ix
     * @return KangxinDiagnosis
     */
    private function findOrCreateDiagnosis($ix) {
        if (array_key_exists($ix, $this->diagnosis)) {
            $diagnosis = $this->diagnosis[$ix];
        } else {
            $diagnosis = new KangxinDiagnosis();
            $this->diagnosis[$ix] = $diagnosis;
        }
        return $diagnosis;
    }
}