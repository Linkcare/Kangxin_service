<?php

class KangxinPatientInfo {
    /** @var stdClass */
    private $originalObject;

    /** @var string*/
    private $sickId;
    /** @var string*/
    private $residenceNo;
    /** @var string*/
    private $name;
    /** @var string*/
    private $sex;
    /** @var string*/
    private $birthDate;
    /** @var number*/
    private $age;
    /** @var string*/
    private $currentAddress;
    /** @var string*/
    private $nation;
    /** @var string*/
    private $identityNumber;
    /** @var string*/
    private $phone;
    /** @var string*/
    private $contactName;
    /** @var string*/
    private $relation;
    /** @var string*/
    private $contactPhone;
    /** @var string*/
    private $admissionTime;
    /** @var string*/
    private $admissionDepartment;
    /** @var string*/
    private $admissionWard;
    /** @var string*/
    private $hospitalAdmission;
    /** @var string*/
    private $dischargeTime;
    /** @var string*/
    private $dischargeDepartment;
    /** @var string*/
    private $dischargeWard;
    /** @var  number*/
    private $actualHospitalDays;
    /** @var string*/
    private $dischargeDiseaseCode;
    /** @var string*/
    private $dischargeMainDiagnosis;
    /** @var string*/
    private $otherDiseaseCodes;
    /** @var string*/
    private $dischargeOtherDiagnoses;
    /** @var string*/
    private $operationCode;
    /** @var string*/
    private $order;
    /** @var string*/
    private $orderDate;
    /** @var string*/
    private $operationName;
    /** @var string*/
    private $operationName1;
    /** @var string*/
    private $operationName2;
    /** @var string*/
    private $operationName3;
    /** @var string*/
    private $operationName4;
    /** @var string*/
    private $operationDate;
    /** @var string*/
    private $operationLevel;
    /** @var string*/
    private $operationSurgeon;
    /** @var string*/
    private $operationType;
    /** @var string*/
    private $drugAllergy;
    /** @var string*/
    private $doctor;
    /** @var string*/
    private $doctorCode;
    /** @var string*/
    private $responsibleNurse;
    /** @var string*/
    private $nthHospital;
    /** @var string*/
    private $hospitalized;
    /** @var string*/
    private $dischargeSituation;
    /** @var string*/
    private $dischargeInstructions;
    /** @var string*/
    private $note;
    /** @var string*/
    private $sickNum;

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
     * @return string
     */
    public function getSex() {
        return $this->sex;
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
     * Age at the time of admission
     *
     * @return number
     */
    public function getAge() {
        return $this->age;
    }

    /**
     * Patient Current Address
     *
     * @return string
     */
    public function getCurrentAddress() {
        return $this->currentAddress;
    }

    /**
     * Patient Nationality
     *
     * @return string
     */
    public function getNation() {
        return $this->nation;
    }

    /**
     *
     * @return string
     */
    public function getNationCode() {
        if ($this->nation == '中国') {
            return 'CN';
        }
        return null;
    }

    /**
     * National ID
     *
     * @return string
     */
    public function getIdentityNumber() {
        return $this->identityNumber;
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
     * @return string
     */
    public function getRelation() {
        return $this->relation;
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
    public function getAdmissionDepartment() {
        return $this->admissionDepartment;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionWard() {
        return $this->admissionWard;
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
    public function getDischargeDepartment() {
        return $this->dischargeDepartment;
    }

    /**
     *
     * @return string
     */
    public function getDischargeWard() {
        return $this->dischargeWard;
    }

    /**
     *
     * @return number
     */
    public function getActualHospitalDays() {
        return $this->actualHospitalDays;
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
        return $this->doctorCode;
    }

    /**
     *
     * @return string
     */
    public function getResponsibleNurse() {
        return $this->responsibleNurse;
    }

    /**
     * Number of admissions
     *
     * @return string
     */
    public function getNthHospital() {
        return $this->nthHospital;
    }

    /**
     *
     * @return string
     */
    public function getHospitalized() {
        return $this->hospitalized;
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
    public function getNote() {
        return $this->note;
    }

    /**
     *
     * @return string
     */
    public function getOrder() {
        return $this->order;
    }

    /**
     *
     * @return string
     */
    public function getOrderDate() {
        return $this->orderDate;
    }

    /**
     *
     * @return string
     */
    public function getOperationCode() {
        return $this->operationCode;
    }

    /**
     *
     * @return string
     */
    public function getOperationName() {
        return $this->operationName;
    }

    /**
     *
     * @return string
     */
    public function getOperationName1() {
        return $this->operationName1;
    }

    /**
     *
     * @return string
     */
    public function getOperationName2() {
        return $this->operationName2;
    }

    /**
     *
     * @return string
     */
    public function getOperationName3() {
        return $this->operationName3;
    }

    /**
     *
     * @return string
     */
    public function getOperationName4() {
        return $this->operationName4;
    }

    /**
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
    public function getOperationLevel() {
        return $this->operationLevel;
    }

    /**
     *
     * @return string
     */
    public function getOperationSurgeon() {
        return $this->operationSurgeon;
    }

    /**
     *
     * @return string
     */
    public function getOperationType() {
        return $this->operationType;
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
        if (in_array($value, ['男', 'm', 'M'])) {
            $value = 'M';
        } elseif ($value) {
            $value = 'F';
        }
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
     * Age at the time of admission
     *
     * @param number $value
     */
    public function setAge($value) {
        $this->age = $value;
    }

    /**
     * Patient Current Address
     *
     * @param string $value
     */
    public function setCurrentAddress($value) {
        $this->currentAddress = $value;
    }

    /**
     * Patient Nationality
     *
     * @param string $value
     */
    public function setNation($value) {
        $this->nation = $value;
    }

    /**
     * National ID
     *
     * @param string $value
     */
    public function setIdentityNumber($value) {
        $this->identityNumber = $value;
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
    public function setAdmissionDepartment($value) {
        $this->admissionDepartment = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionWard($value) {
        $this->admissionWard = $value;
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
    public function setDischargeDepartment($value) {
        $this->dischargeDepartment = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setDischargeWard($value) {
        $this->dischargeWard = $value;
    }

    /**
     *
     * @param number $value
     */
    public function setActualHospitalDays($value) {
        $this->actualHospitalDays = $value;
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
    public function setDoctorCode($value) {
        $this->doctorCode = $value;
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
    public function setNthHospital($value) {
        $this->nthHospital = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setHospitalized($value) {
        $this->hospitalized = $value;
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
    public function setNote($value) {
        $this->note = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOrder($value) {
        $this->order = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOrder($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOrderDate($value) {
        $this->orderDate = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOrderDate($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationCode($value) {
        $this->operationCode = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationCode($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationSurgeon($value) {
        $this->operationSurgeon = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationSurgeon($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName($value) {
        $this->operationName = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationName($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName1($value) {
        $this->operationName1 = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationName1($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName2($value) {
        $this->operationName2 = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationName2($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName3($value) {
        $this->operationName3 = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationName3($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName4($value) {
        $this->operationName4 = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationName4($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->operationDate = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationDate($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationLevel($value) {
        $this->operationLevel = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationLevel($v);
        }
    }

    /**
     *
     * @param string $value
     */
    public function setOperationType($value) {
        $this->operationType = $value;

        $list = explode(',', $value);
        foreach ($list as $ix => $v) {
            $procedure = $this->findOrCreateProcedure($ix);
            $procedure->setOperationType($v);
        }
    }

    /**
     * ******* METHODS *******
     */
    /**
     *
     * @param int $ix
     * @return KangxinProcedure
     */
    private function findOrCreateProcedure($ix) {
        if (array_key_exists($ix, $this->procedures)) {
            $procedure = $this->procedures[$ix];
        } else {
            $procedure = new KangxinProcedure();
            $this->procedures[$ix] = $procedure;
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
     *
     * @param stdClass $info
     * @return KangxinPatientInfo
     */
    static function fromJson($info) {
        /*
         * Remove the "total" property included in al patient data, because it is not a patient information. It is the total number of records in the
         * DB
         */
        unset($info->total);
        $patientInfo = new KangxinPatientInfo();

        $patientInfo->originalObject = $info;
        $jsonVars = get_object_vars($info);
        foreach ($jsonVars as $name => $value) {
            $setterFn = 'set' . strtoupper(substr($name, 0, 1)) . substr($name, 1);
            // The properties returned by the Kangxin service match the names of the members of this class
            if (method_exists($patientInfo, $setterFn))
                $patientInfo->{$setterFn}($value);
        }

        return $patientInfo;
    }
}