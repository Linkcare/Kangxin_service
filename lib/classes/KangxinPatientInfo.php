<?php

class KangxinPatientInfo {
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
    private $nation;
    /** @var string*/
    private $idCardNo;
    /** @var string*/
    private $familyPhone;
    /** @var string*/
    private $associationName;
    /** @var string*/
    private $societyRelation;
    /** @var string*/
    private $associationPhone;
    /** @var string*/
    private $admissionTime;
    /** @var string*/
    private $admissionDepartment;
    /** @var string*/
    private $admissionWard;
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
    private $operationName;
    /** @var string*/
    private $operationDate;
    /** @var string*/
    private $operationLevel;
    /** @var string*/
    private $operationSurgeon;
    /** @var string*/
    private $drugAllergy;
    /** @var string*/
    private $doctor;
    /** @var string*/
    private $responsibleNurse;
    /** @var string*/
    private $hospitalAdmission;
    /** @var string*/
    private $hospitalized;
    /** @var string*/
    private $dischargeSituation;
    /** @var string*/
    private $dischargeInstructions;

    /**
     * ******* GETTERS *******
     */
    /**
     *
     * @return string
     */
    public function getSickId() {
        return $this->sickId;
    }

    /**
     *
     * @return string
     */
    public function getResidenceNo() {
        return $this->residenceNo;
    }

    /**
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function getSex() {
        return $this->sex;
    }

    /**
     *
     * @return string
     */
    public function getBirthDate() {
        return $this->birthDate;
    }

    /**
     *
     * @return number
     */
    public function getAge() {
        return $this->age;
    }

    /**
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
    public function getIdCardNo() {
        return $this->idCardNo;
    }

    /**
     *
     * @return string
     */
    public function getFamilyPhone() {
        if (!startsWith('+', $this->familyPhone)) {
            return '+86' . $this->familyPhone;
        }
        return $this->familyPhone;
    }

    /**
     *
     * @return string
     */
    public function getAssociationName() {
        return $this->associationName;
    }

    /**
     *
     * @return string
     */
    public function getSocietyRelation() {
        return $this->societyRelation;
    }

    /**
     *
     * @return string
     */
    public function getAssociationPhone() {
        return $this->associationPhone;
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
     *
     * @return string
     */
    public function getDischargeDiseaseCode() {
        return $this->dischargeDiseaseCode;
    }

    /**
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
    public function getResponsibleNurse() {
        return $this->responsibleNurse;
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
     * ******* SETTERS *******
     */
    /**
     *
     * @param string $sickId
     */
    public function setSickId($sickId) {
        $this->sickId = $sickId;
    }

    /**
     *
     * @param string $residenceNo
     */
    public function setResidenceNo($residenceNo) {
        $this->residenceNo = $residenceNo;
    }

    /**
     *
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     *
     * @param string $sex
     */
    public function setSex($sex) {
        if (in_array($sex, ['男', 'm', 'M'])) {
            $sex = 'M';
        } elseif ($sex) {
            $sex = 'F';
        }
        $this->sex = $sex;
    }

    /**
     *
     * @param string $birthDate
     */
    public function setBirthDate($birthDate) {
        $this->birthDate = $birthDate;
    }

    /**
     *
     * @param number $sickId
     */
    public function setAge($age) {
        $this->age = $age;
    }

    /**
     *
     * @param string $nation
     */
    public function setNation($nation) {
        $this->nation = $nation;
    }

    /**
     *
     * @param string $idCardNo
     */
    public function setIdCardNo($idCardNo) {
        $this->idCardNo = $idCardNo;
    }

    /**
     *
     * @param string $familyPhone
     */
    public function setFamilyPhone($familyPhone) {
        $this->familyPhone = $familyPhone;
    }

    /**
     *
     * @param string $associationName
     */
    public function setAssociationName($associationName) {
        $this->associationName = $associationName;
    }

    /**
     *
     * @param string $societyRelation
     */
    public function setSocietyRelation($societyRelation) {
        $this->societyRelation = $societyRelation;
    }

    /**
     *
     * @param string $associationPhone
     */
    public function setAssociationPhone($associationPhone) {
        $this->associationPhone = $associationPhone;
    }

    /**
     *
     * @param string $admissionTime
     */
    public function setAdmissionTime($admissionTime) {
        $this->admissionTime = $admissionTime;
    }

    /**
     *
     * @param string $admissionDepartment
     */
    public function setAdmissionDepartment($admissionDepartment) {
        $this->admissionDepartment = $admissionDepartment;
    }

    /**
     *
     * @param string $admissionWard
     */
    public function setAdmissionWard($admissionWard) {
        $this->admissionWard = $admissionWard;
    }

    /**
     *
     * @param string $dischargeTime
     */
    public function setDischargeTime($dischargeTime) {
        $this->dischargeTime = $dischargeTime;
    }

    /**
     *
     * @param string $dischargeDepartment
     */
    public function setDischargeDepartment($dischargeDepartment) {
        $this->dischargeDepartment = $dischargeDepartment;
    }

    /**
     *
     * @param string $dischargeWard
     */
    public function setDischargeWard($dischargeWard) {
        $this->dischargeWard = $dischargeWard;
    }

    /**
     *
     * @param number $actualHospitalDays
     */
    public function setActualHospitalDays($actualHospitalDays) {
        $this->actualHospitalDays = $actualHospitalDays;
    }

    /**
     *
     * @param string $dischargeDiseaseCode
     */
    public function setDischargeDiseaseCode($dischargeDiseaseCode) {
        $this->dischargeDiseaseCode = $dischargeDiseaseCode;
    }

    /**
     *
     * @param string $dischargeMainDiagnosis
     */
    public function setDischargeMainDiagnosis($dischargeMainDiagnosis) {
        $this->dischargeMainDiagnosis = $dischargeMainDiagnosis;
    }

    /**
     *
     * @param string $otherDiseaseCodes
     */
    public function setOtherDiseaseCodes($otherDiseaseCodes) {
        $this->otherDiseaseCodes = $otherDiseaseCodes;
    }

    /**
     *
     * @param string $dischargeOtherDiagnoses
     */
    public function setDischargeOtherDiagnoses($dischargeOtherDiagnoses) {
        $this->dischargeOtherDiagnoses = $dischargeOtherDiagnoses;
    }

    /**
     *
     * @param string $operationCode
     */
    public function setOperationCode($operationCode) {
        $this->operationCode = $operationCode;
    }

    /**
     *
     * @param string $operationName
     */
    public function setOperationName($operationName) {
        $this->operationName = $operationName;
    }

    /**
     *
     * @param string $operationDate
     */
    public function setOperationDate($operationDate) {
        $this->operationDate = $operationDate;
    }

    /**
     *
     * @param string $operationLevel
     */
    public function setOperationLevel($operationLevel) {
        $this->operationLevel = $operationLevel;
    }

    /**
     *
     * @param string $operationSurgeon
     */
    public function setOperationSurgeon($operationSurgeon) {
        $this->operationSurgeon = $operationSurgeon;
    }

    /**
     *
     * @param string $drugAllergy
     */
    public function setDrugAllergy($drugAllergy) {
        $this->drugAllergy = $drugAllergy;
    }

    /**
     *
     * @param string $doctor
     */
    public function setDoctor($doctor) {
        $this->doctor = $doctor;
    }

    /**
     *
     * @param string $responsibleNurse
     */
    public function setResponsibleNurse($responsibleNurse) {
        $this->responsibleNurse = $responsibleNurse;
    }

    /**
     *
     * @param string $hospitalAdmission
     */
    public function setHospitalAdmission($hospitalAdmission) {
        $this->hospitalAdmission = $hospitalAdmission;
    }

    /**
     *
     * @param string $hospitalized
     */
    public function setHospitalized($hospitalized) {
        $this->hospitalized = $hospitalized;
    }

    /**
     *
     * @param string $dischargeSituation
     */
    public function setDischargeSituation($dischargeSituation) {
        $this->dischargeSituation = $dischargeSituation;
    }

    /**
     *
     * @param string $dischargeInstructions
     */
    public function setDischargeInstructions($dischargeInstructions) {
        $this->dischargeInstructions = $dischargeInstructions;
    }

    /**
     *
     * @param stdClass $info
     * @return KangxinPatientInfo
     */
    static function fromJson($info) {
        $patientInfo = new KangxinPatientInfo();

        $jsonVars = get_object_vars($info);
        foreach ($jsonVars as $name => $value) {
            $setterFn = 'set' . strtoupper(substr($name, 0, 1)) . substr($name, 1);
            // The properties returned by the Kangxin service match the names of the members of this class
            if (method_exists($patientInfo, $setterFn))
                $patientInfo->$setterFn($value);
        }

        return $patientInfo;
    }
}