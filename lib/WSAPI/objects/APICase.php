<?php

class APICase {
    private $id;
    private $userName;
    private $fullName;
    private $name;
    private $surname;
    private $nickname;
    private $bdate;
    private $gender;
    private $timezone;
    /* @var APIIdentifier[] $identifiers */
    private $identifiers = [];
    /** @var LinkcareSoapAPI $api */
    private $api;

    public function __construct() {
        $this->api = LinkcareSoapAPI::getInstance();
    }

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APICase
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $case = new APICase();
        $case->id = trim($xmlNode->ref);
        $case->userName = trim($xmlNode->username);
        if ($xmlNode->data) {
            $case->fullName = trim($xmlNode->data->full_name);
            $case->name = trim($xmlNode->data->name);
            $case->surname = trim($xmlNode->data->surname);
            $case->nickname = trim($xmlNode->data->nickname);
            $case->bdate = trim($xmlNode->data->bdate);
            $case->gender = trim($xmlNode->data->gender);
            $case->timezone = trim($xmlNode->data->timezone);
        }
        $identifiers = [];
        if ($xmlNode->identifiers) {
            foreach ($xmlNode->identifiers->identifier as $idNode) {
                $identifiers[] = APIIdentifier::parseXML($idNode);
            }
            $case->identifiers = array_filter($identifiers);
        }
        return $case;
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */
    /**
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function getUsername() {
        return $this->userName;
    }

    /**
     *
     * @return string
     */
    public function getFullName() {
        return $this->fullName;
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
    public function getSurname() {
        return $this->surname;
    }

    /**
     *
     * @return string
     */
    public function getNickname() {
        return $this->nickname;
    }

    /**
     *
     * @return string
     */
    public function getBirthdate() {
        return $this->bdate;
    }

    /**
     *
     * @return string
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     *
     * @return string
     */
    public function getTimezone() {
        return $this->timezone;
    }

    /**
     *
     * @return APIIdentifier[]
     */
    public function getIdentifiers() {
        return $this->identifiers;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */

    /**
     *
     * @param int $maxRes
     * @param int $offset
     * @param TaskFilter $filter
     * @param boolean $ascending
     * @return APITask[]
     */
    public function getTaskList($maxRes = null, $offset = null, $filter = null, $ascending = true) {
        if (!$filter) {
            $filter = new TaskFilter();
        }
        $filter->setObjectType('TASKS');
        return $this->api->case_get_task_list($this->id, $maxRes, $offset, $filter, $ascending);
    }
}