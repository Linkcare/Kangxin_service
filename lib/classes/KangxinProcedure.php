<?php

class KangxinProcedure {
    /** @var string*/
    private $operationCode;
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

    /**
     * ******* GETTERS *******
     */

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
     * ******* SETTERS *******
     */

    /**
     *
     * @param string $value
     */
    public function setOperationCode($value) {
        $this->operationCode = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName($value) {
        $this->operationName = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName1($value) {
        $this->operationName1 = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName2($value) {
        $this->operationName2 = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName3($value) {
        $this->operationName3 = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationName4($value) {
        $this->operationName4 = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationDate($value) {
        $this->operationDate = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationSurgeon($value) {
        $this->operationSurgeon = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationLevel($value) {
        $this->operationLevel = $value;
    }

    /**
     *
     * @param string $value
     */
    public function setOperationType($value) {
        $this->operationType = $value;
    }
}