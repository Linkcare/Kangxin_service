<?php

class RecordPool {
    /** @var int */
    private $id;
    /** @var string */
    private $patientId;
    /** @var string */
    private $episodeId;
    /** @var string */
    private $admissionDate;
    /** @var string */
    private $creationDate;
    /** @var string */
    private $lastUpdate;
    /** @var string */
    private $recordContent;
    /** @var boolean */
    private $recordContentModified;
    /** @var int */
    private $changed;
    private $updateNeeded = [];

    public function __construct($patientId = null, $episodeId = null, $admissionDate = null) {
        $this->patientId = $patientId;
        $this->episodeId = $episodeId;
        $this->admissionDate = $admissionDate;
    }

    /**
     * ******* GETTERS *******
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Id of the patient
     *
     * @return string
     */
    public function getPatientId() {
        return $this->patientId;
    }

    /**
     * Id of the patient episode
     *
     * @return string
     */
    public function getEpisodeId() {
        return $this->episodeId;
    }

    /**
     * Start date of the episode
     *
     * @return string
     */
    public function getAdmissionDate() {
        return $this->admissionDate;
    }

    /**
     * Last update date
     *
     * @return string
     */
    public function getLastUpdate() {
        return $this->lastUpdate;
    }

    /**
     * JSON object with the content of the record
     *
     * @return stdClass
     */
    public function getRecordContent() {
        return json_decode($this->recordContent);
    }

    /**
     * Returns one of the following values
     * <ul>
     * <li>0: The record has no changes and has been processed (imported in the Linkcare platform)</li>
     * <li>1: The record has been updated with the information received from Kangxin</li>
     * <li>2: The record generated an error while trying to import it in the Linkcare platform</li>
     * </ul>
     *
     * @return int
     */
    public function getChanged() {
        return $this->changed;
    }

    /**
     * ******* SETTERS *******
     */
    /**
     *
     * @param string $value
     */
    public function setPatientId($value) {
        if ($value == $this->patientId) {
            return;
        }
        $this->patientId = $value;
        $this->updateNeeded[':patientId'] = 'ID_PATIENT'; // Name of the field in DB
    }

    /**
     *
     * @param string $value
     */
    public function setEpisodeId($value) {
        if ($value == $this->episodeId) {
            return;
        }
        $this->episodeId = $value;
        $this->updateNeeded[':episodeId'] = 'ID_EPISODE'; // Name of the field in DB
    }

    /**
     *
     * @param string $value
     */
    public function setAdmissionDate($value) {
        if ($value == $this->admissionDate) {
            return;
        }
        $this->admissionDate = $value;
        $this->updateNeeded[':admissionDate'] = 'ADMISSION_DATE'; // Name of the field in DB
    }

    /**
     * Sets the 'changed' mark to one of the following values
     * <ul>
     * <li>0: The record has no changes and has been processed (imported in the Linkcare platform)</li>
     * <li>1: The record has been updated with the information received from Kangxin</li>
     * <li>2: The record generated an error while trying to import it in the Linkcare platform</li>
     * </ul>
     *
     * @param string $value
     */
    public function setChanged($value) {
        if ($value == $this->changed) {
            return;
        }
        $this->changed = $value;
        $this->updateNeeded[':changed'] = 'CHANGED'; // Name of the field in DB
    }

    /**
     * JSON object with the content of the record
     *
     * @param stdClass $value
     */
    public function setRecordContent($jsonObj) {
        if (!$jsonObj) {
            return;
        }
        $value = json_encode($jsonObj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$value) {
            throw new ServiceException(ErrorCodes::INVALID_JSON);
        }

        if ($this->equals($jsonObj)) {
            return;
        }

        $this->recordContent = $value;
        $this->setChanged(1);
        $this->recordContentModified = true;
    }

    /**
     * ******* METHODS *******
     */
    /**
     *
     * @param int $id
     * @return RecordPool[]
     */
    static public function getInstance($patientId, $episodeId) {
        $arrVariables[':patientId'] = $patientId;
        $arrVariables[':episodeId'] = $episodeId;
        $sql = 'SELECT * FROM RECORD_POOL WHERE ID_PATIENT=:patientId AND ID_EPISODE=:episodeId';
        $rst = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
        $obj = null;
        if ($rst->Next()) {
            $obj = self::loadDBRecord($rst);
        }

        return $obj;
    }

    /**
     *
     * @param DbManagerResults $rst
     * @return RecordPool
     */
    static private function loadDBRecord($rst) {
        $obj = new RecordPool();
        $obj->id = $rst->GetField('ID_RECORD_POOL');
        $obj->creationDate = $rst->GetField('CREATION_DATE');
        $obj->lastUpdate = $rst->GetField('LAST_UPDATE');
        $obj->patientId = $rst->GetField('ID_PATIENT');
        $obj->episodeId = $rst->GetField('ID_EPISODE');
        $obj->admissionDate = $rst->GetField('ADMISSION_DATE');
        $obj->recordContent = $rst->GetField('RECORD_CONTENT');
        $obj->changed = intval($rst->GetField('CHANGED'));

        return $obj;
    }

    /**
     *
     * @return DbError
     */
    public function save() {
        $isNew = false;
        $now = currentDate();

        if (!$this->patientId || !$this->episodeId || !$this->recordContent) {
            return;
        }

        if (!$this->id) {
            // Create a new record
            $this->id = Database::getInstance()->getNextSequence('ID_RECORD_POOL_SEQ');
            $isNew = true;
            $this->creationDate = $now;
            $arrVariables[':creationDate'] = $this->creationDate;
        }

        $this->lastUpdate = $now;
        $arrVariables[':id'] = $this->id;
        $arrVariables[':lastUpdate'] = $this->lastUpdate;
        $arrVariables[':patientId'] = $this->patientId;
        $arrVariables[':admissionDate'] = $this->admissionDate;
        $arrVariables[':episodeId'] = $this->episodeId;
        $arrVariables[':changed'] = $this->changed;
        $arrBlobVariables = null;
        if ($this->recordContentModified) {
            $arrVariables[':clob_recordContent'] = $this->recordContent;
            $sqlBytesFieldName = ', RECORD_CONTENT';
            $sqlBytesInsert = ', :clob_recordContent';
            $sqlBytesUpdate = 'RECORD_CONTENT = :clob_recordContent';
            $arrBlobVariables = [':clob_recordContent' => 'RECORD_CONTENT'];
        }

        $sql = null;
        if ($isNew) {
            $sql = "INSERT INTO RECORD_POOL (ID_RECORD_POOL, ID_PATIENT, ID_EPISODE, ADMISSION_DATE, CREATION_DATE, LAST_UPDATE, CHANGED $sqlBytesFieldName)
                    VALUES (:id, :patientId, :episodeId, :admissionDate, :creationDate, :lastUpdate, :changed $sqlBytesInsert)";
        } elseif (count($this->updateNeeded) > 0 || $this->recordContentModified) {
            $sqlUpdates = [];
            foreach ($this->updateNeeded as $varName => $fieldName) {
                $sqlUpdates[] .= "$fieldName = $varName";
            }
            if ($sqlBytesUpdate) {
                $sqlUpdates[] = $sqlBytesUpdate;
            }
            $updateStr = implode(',', $sqlUpdates);
            $sql = "UPDATE RECORD_POOL SET $updateStr WHERE ID_RECORD_POOL=:id";
        }

        $error = null;
        if ($sql) {
            Database::getInstance()->ExecuteLOBQuery($sql, $arrVariables, $arrBlobVariables);
            $error = Database::getInstance()->getError();
            if (!$error->getCode()) {
                $this->updateNeeded = [];
                $this->recordContentModified = false;
            }
        }

        return $error;
    }

    /**
     *
     * @param stdClass $recordContent
     */
    public function equals($recordContent) {
        /*
         * We must ensure that the properties of the object are sorted in the same way to do a correct comparation
         */
        if (!$this->recordContent) {
            return false;
        }
        $current = json_decode($this->recordContent, true);
        ksort($current);
        $toCompare = json_decode(json_encode($recordContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        ksort($toCompare);

        return (json_encode($current) == json_encode($toCompare));
    }

    /**
     *
     * @param int $pageSize Number of records to return
     * @param int $pageNum Returns records of this page (base 1)
     * @return RecordPool[]
     */
    public function loadChanged($pageSize, $pageNum) {
        $records = [];
        $startOffset = max($pageNum - 1, 0) * $pageSize + 1;
        $endOffset = $startOffset + $pageSize;

        $arrVariables[':startOffset'] = $startOffset;
        $arrVariables[':endOffset'] = $endOffset;
        $sql = 'SELECT * FROM (
                	SELECT rp.*,ROW_NUMBER() OVER(ORDER BY LAST_UPDATE,ID_RECORD_POOL) RN FROM RECORD_POOL rp WHERE CHANGED=1 ORDER BY ID_PATIENT,ADMISSION_DATE ASC 
                ) WHERE RN >=:startOffset AND RN<:endOffset';
        $rst = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
        while ($rst->Next()) {
            $records[] = self::loadDBRecord($rst);
        }
        return $records;
    }

    /**
     * Returns the total number of records with changes
     *
     * @return number
     */
    static public function countTotalChanged() {
        $total = 0;
        $sql = 'SELECT COUNT(ID_RECORD_POOL) AS TOTAL FROM RECORD_POOL WHERE CHANGED=1';
        $rst = Database::getInstance()->ExecuteQuery($sql);
        if ($rst->Next()) {
            $total = $rst->GetField('TOTAL');
        }
        return $total;
    }
}