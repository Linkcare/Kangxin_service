<?php

class KangxinProcedure {
    /**
     * This member indicates if any change in the properties of the object must be tracked
     *
     * @var boolean
     */
    private $trackChanges = true;
    private $changeList = [];

    /** @var string*/
    private $applyOperatNo;
    /** @var string*/
    private $processOrder;
    /** @var string*/
    private $operationCode;
    /** @var string*/
    private $operationDoctor;
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
    private $operationType;

    public function __construct($operationId = null) {
        $this->applyOperatNo = $operationId;
    }

    /**
     * ******* GETTERS *******
     */

    /**
     *
     * @return string
     */
    public function getApplyOperatNo() {
        return $this->applyOperatNo;
    }

    /**
     *
     * @return string
     */
    public function getProcessOrder() {
        return $this->processOrder;
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
    public function getOperationDoctor() {
        return $this->operationDoctor;
    }

    /**
     *
     * @return string
     */
    public function getOperationDoctorCode() {
        $parts = explode('/', $this->operationDoctor);
        if (count($parts) > 1) {
            return $parts[1];
        }

        return null;
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
    public function getOperationType() {
        return $this->operationType;
    }

    /**
     * ******* SETTERS *******
     */

    /**
     *
     * @param string $value
     */
    public function setApplyOperatNo($value) {
        $this->applyOperatNo = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setProcessOrder($value) {
        $this->trackPropertyChange('processOrder', $value, $this->processOrder);
        $this->processOrder = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationCode($value) {
        $this->trackPropertyChange('operationCode', $value, $this->operationCode);
        $this->operationCode = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDoctor($value) {
        $this->trackPropertyChange('operationDoctor', $value, $this->operationDoctor);
        $this->operationDoctor = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName($value) {
        $this->trackPropertyChange('operationName', $value, $this->operationName);
        $this->operationName = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName1($value) {
        $this->trackPropertyChange('operationName1', $value, $this->operationName1);
        $this->operationName1 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName2($value) {
        $this->trackPropertyChange('operationName2', $value, $this->operationName2);
        $this->operationName2 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName3($value) {
        $this->trackPropertyChange('operationName3', $value, $this->operationName3);
        $this->operationName3 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName4($value) {
        $this->trackPropertyChange('operationName4', $value, $this->operationName4);
        $this->operationName4 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->trackPropertyChange('operationDate', $value, $this->operationDate);
        $this->operationDate = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationLevel($value) {
        $this->trackPropertyChange('operationLevel', $value, $this->operationLevel);
        $this->operationLevel = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationType($value) {
        $this->trackPropertyChange('operationType', $value, $this->operationType);
        $this->operationType = trim($value);
    }

    /**
     * ******* METHODS *******
     */

    /**
     *
     * @param stdClass $operationInfo
     */
    public function update($operationInfo) {
        $this->setOperationType($operationInfo->operationType);
        $this->setProcessOrder($operationInfo->processOrder);
        $this->setOperationDoctor($operationInfo->operationDoctor);
        $this->setOperationName($operationInfo->operationName);
        $this->setOperationDate($operationInfo->operationDate);
        $this->setOperationName1($operationInfo->operationName1);
        $this->setOperationName2($operationInfo->operationName2);
        $this->setOperationName3($operationInfo->operationName3);
        $this->setOperationName4($operationInfo->operationName4);
        $this->setOperationLevel($operationInfo->operationLevel);
    }

    /**
     * Returns true if any property of the object has been modified
     *
     * @return boolean
     */
    public function hasChanges() {
        return count($this->changeList) > 0;
    }

    /**
     *
     * @param stdClass $operationInfo
     * @return KangxinProcedure
     */
    static public function fromJson($operationInfo) {
        $procedure = new KangxinProcedure($operationInfo->applyOperatNo);
        /* This is the first time that we create the object, so it is not necessary to track the changes */
        $procedure->trackChanges = false;
        $procedure->update($operationInfo);
        /* From this moment we want to track the changes in any of the object properties */
        $procedure->trackChanges = true;
        return $procedure;
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
}