<?php

class KangxinProcedure {
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
        $this->processOrder = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationCode($value) {
        $this->operationCode = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDoctor($value) {
        $this->operationDoctor = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName($value) {
        $this->operationName = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName1($value) {
        $this->operationName1 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName2($value) {
        $this->operationName2 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName3($value) {
        $this->operationName3 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName4($value) {
        $this->operationName4 = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->operationDate = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationLevel($value) {
        $this->operationLevel = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setOperationType($value) {
        $this->operationType = trim($value);
    }
}