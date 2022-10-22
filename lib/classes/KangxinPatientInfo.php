<?php

class KangxinPatientInfo {
    /** @var stdClass */
    private $originalObject;

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
        if (!startsWith('+', $this->phone)) {
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
    public function getDoctorCode() {
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
        $this->name = $value;
    }

    /**
     * Patient gender
     *
     * @param string $value
     */
    public function setSex($value) {
        $this->sex = $value;
    }

    /**
     * Patient date of birth
     *
     * @param string $value
     */
    public function setBirthDate($value) {
        $this->birthDate = $value;
    }

    /**
     * Patient Ethnicity
     *
     * @param string $value
     */
    public function setEthnicity($value) {
        $this->ethnicity = $value;
    }

    /**
     * Patient Country
     *
     * @param string $value
     */
    public function setNationality($value) {
        $this->nationality = $value;
    }

    /**
     * Patient identifier
     *
     * @param string $value
     */
    public function setIdCard($value) {
        $this->idCard = $value;
    }

    /**
     * Patient identifier type (national ID, passport...)
     *
     * @param string $value
     */
    public function setIdCardType($value) {
        $this->idCardType = $value;
    }

    /**
     * Mobile phone
     *
     * @param string $value
     */
    public function setPhone($value) {
        $this->phone = $value;
    }

    /**
     * Contact (associate) name
     *
     * @param string $value
     */
    public function setContactName($value) {
        $this->contactName = $value;
    }

    /**
     * Contact (associate) Relationship
     *
     * @param string $value
     */
    public function setRelation($value) {
        $this->relation = $value;
    }

    /**
     * Marital status
     *
     * @param string $value
     */
    public function setMarital($value) {
        $this->marital = $value;
    }

    /**
     * Patient type
     *
     * @param string $value
     */
    public function setKind($value) {
        $this->kind = $value;
    }

    /**
     * Patient profession
     *
     * @param string $value
     */
    public function setProfession($value) {
        $this->profession = $value;
    }

    /**
     * Contact (associate) phone
     *
     * @param string $value
     */
    public function setContactPhone($value) {
        $this->contactPhone = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionTime($value) {
        $this->admissionTime = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionDept($value) {
        $this->admissionDept = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setHospitalAdmission($value) {
        $this->hospitalAdmission = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeTime($value) {
        $this->dischargeTime = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeDept($value) {
        $this->dischargeDept = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeStatus($value) {
        $this->dischargeStatus = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionDiag($value) {
        $this->admissionDiag = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeDiag($value) {
        $this->dischargeDiag = $value;
    }

    /**
     * Main diagnosis code
     *
     * @param string $value
     */
    public function setDischargeDiseaseCode($value) {
        $this->dischargeDiseaseCode = $value;
    }

    /**
     * Main diagnosis name
     *
     * @param string $value
     */
    public function setDischargeMainDiagnosis($value) {
        $this->dischargeMainDiagnosis = $value;
    }

    /**
     * Other diagnosis codes
     *
     * @param string $value
     */
    public function setOtherDiseaseCodes($value) {
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
        $this->drugAllergy = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDoctor($value) {
        $this->doctor = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setResponsibleNurse($value) {
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
        $this->hospitalized = $value;
    }

    /**
     * Id of the operation
     *
     * @param string $value
     */
    public function setApplyOperatno($value) {
        $this->applyOperatNo = $value;
    }

    /**
     * Operation date
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->operationDate = $value;
    }

    /**
     * Procedure Order Name
     *
     * @param string $value
     */
    public function setProcessOrder($value) {
        $this->processOrder = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeSituation($value) {
        $this->dischargeSituation = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeInstructions($value) {
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
     *
     * @param int $operationId
     * @return KangxinProcedure
     */
    private function findOrCreateProcedure($operationId) {
        if (array_key_exists($operationId, $this->procedures)) {
            $procedure = $this->procedures[$operationId];
        } else {
            $procedure = new KangxinProcedure($operationId);
            $this->procedures[$operationId] = $procedure;
        }
        return $procedure;
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
        $procedure = $this->findOrCreateProcedure($info->applyOperatNo);
        $procedure->setOperationType($info->operationType);
        $procedure->setProcessOrder($info->processOrder);
        $procedure->setOperationDoctor($info->operationDoctor);
        $procedure->setOperationName($info->operationName);
        $procedure->setOperationDate($info->operationDate);
        $procedure->setOperationName1($info->operationName1);
        $procedure->setOperationName2($info->operationName2);
        $procedure->setOperationName3($info->operationName3);
        $procedure->setOperationName4($info->operationName4);
        $procedure->setOperationLevel($info->operationLevel);
    }

    /**
     * Creates a KangxinPatientInfo object from the information received from the Kangxin hospital
     *
     * @param stdClass $info
     * @return KangxinPatientInfo
     */
    static function fromJson($info) {
        $patientInfo = new KangxinPatientInfo();
        if (!$info) {
            return $patientInfo;
        }

        $patientInfo->originalObject = $info;
        $jsonVars = get_object_vars($info);
        foreach ($jsonVars as $name => $value) {
            $setterFn = 'set' . strtoupper(substr($name, 0, 1)) . substr($name, 1);
            // The properties returned by the Kangxin service match the names of the members of this class
            if (method_exists($patientInfo, $setterFn))
                $patientInfo->{$setterFn}($value);
        }

        $patientInfo->addOperation($info);

        return $patientInfo;
    }
}