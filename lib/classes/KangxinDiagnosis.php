<?php

class KangxinDiagnosis {
    /** @var string*/
    private $code;
    /** @var string*/
    private $name;

    public function __construct($code = null, $name = null) {
        $this->code = $code;
        $this->name = $name;
    }

    /**
     * ******* GETTERS *******
     */

    /**
     *
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * ******* SETTERS *******
     */

    /**
     *
     * @param string $value
     */
    public function setCode($value) {
        $this->code = trim($value);
    }

    /**
     *
     * @param string $value
     */
    public function setName($value) {
        $this->name = trim($value);
    }
}