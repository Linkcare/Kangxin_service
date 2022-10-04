<?php

class KangxinAPI {
    private static $instance;
    private $endpoint;

    private function __construct($endpoint) {
        $this->endpoint = trim($endpoint, '/') . '/';
    }

    static public function getInstance() {
        if (!self::$instance) {
            self::$instance = new KangxinAPI($GLOBALS['KANGXIN_API_URL']);
        }
        return self::$instance;
    }

    /**
     * Invokes the Kangxin API to retrieve a list of patients that should be imported in the Linkcare platform
     *
     * @param stdClass $obj
     * @throws ServiceException
     * @return KangxinPatientInfo[]
     */
    public function requestPatientList($pageSize, $pageNum) {
        if ($GLOBALS['SIMULATE_KANGXIN_API']) {
            return $this->simulatedData();
        }

        $params['pageSize'] = $pageSize;
        $params['pageNum'] = $pageNum;
        $resp = $this->invokeAPI('sickInfo/personInfos', $params);

        if (!is_array($resp)) {
            throw new ServiceException(ErrorCodes::API_INVALID_DATA_FORMAT, 'sickInfo/personInfos function expects an array as response');
        }

        $patients = [];
        foreach ($resp as $info) {
            $patients[] = KangxinPatientInfo::fromJson($info);
        }

        return $patients;
    }

    /**
     * Invokes a REST function in the Kangxin API
     *
     * @param string $function
     * @param string[] $params
     * @throws ServiceException
     * @return stdClass
     */
    private function invokeAPI($function, $params, $sendAsJson = true) {
        $this->httpStatus = null;

        $endpoint = $this->endpoint . $function;
        $errorMsg = null;
        $headers = [];

        if ($sendAsJson) {
            $headers[] = 'Content-Type: application/json';
            $params = json_encode($params);
        }

        $options = [CURLOPT_POST => true, CURLOPT_HEADER => false, CURLOPT_AUTOREFERER => true, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => $params, CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_RETURNTRANSFER => 1];
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, $options);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, [$this, "handleHeaderLine"]);

        // Check if any error occurred
        if (curl_errno($curl)) {
            $errorMsg = 'CURL error: ' . curl_error($curl);
        }

        $APIResponse = curl_exec($curl);
        if ($APIResponse === false) {
            $errorMsg = 'CURL error: ' . curl_error($curl);
        }

        curl_close($curl);

        if ($errorMsg) {
            throw new ServiceException(ErrorCodes::API_COMM_ERROR, $errorMsg);
        }
        if (!$APIResponse && !startsWith('2', $this->httpStatus)) {
            // The did not provide any information but responded with an HTTP error status
            throw new ServiceException(ErrorCodes::API_ERROR_STATUS, $this->httpStatus);
        }
        return $this->parseAPIResponse($APIResponse);
    }

    /**
     * Handles header lines returned by a CURL command
     *
     * @param resource $curl
     * @param string $header_line
     * @return number
     */
    function handleHeaderLine($curl, $header_line) {
        $matches = null;
        if (preg_match('/^HTTP[^\s]*\s(\d+)*/', $header_line, $matches)) {
            $this->httpStatus = $matches[1];
        }
        return strlen($header_line);
    }

    /**
     * Parses the response of the Kangxin API and checks if any error was reported.
     * All responses from the API contain 3 fields:+
     * <ul>
     * <li>code: an error code (or empty if no error occurred)</li>
     * <li>message: Explanation of the error (if any)</li>
     * <li>result: Contents of the response of the API function</li>
     * </ul>
     * If an error is reported by the API, an exception will be thrown. Otherwise the function returns the contents of the response
     *
     * @param string $APIResponse
     * @throws ServiceException::
     * @return stdClass
     */
    private function parseAPIResponse($APIResponse) {
        $result = json_decode($APIResponse);

        if (!$result) {
            // Error decoding JSON format
            throw new ServiceException(ErrorCodes::API_INVALID_DATA_FORMAT);
        }

        $errorCode = $result->code;
        if (!isNullOrEmpty($errorCode) && $errorCode != 0) {
            // API returned an error message
            throw new ServiceException(ErrorCodes::API_FUNCTION_ERROR, trim($result->code . ': ' . $result->msg));
        }

        return $result->result;
    }

    /**
     * Returns simulated patient information
     *
     * @return KangxinPatientInfo[]
     */
    private function simulatedData() {
        $sample1 = '{
      "sickId": "19680312",
      "sickNum": "SickNum 1",
      "name": "PCI Test patient",
      "sex": "女",
      "birthDate": "1968-03-12",
      "age": 54,
      "currentAddress": "Camino del Discharge 99",
      "nation": "中国",
      "identityNumber": "510212194412094125",
      "phone": "13594125051",
      "contactName": "Associate name 1",
      "contactPhone": "Associate phone 1",
      "relation": "Associate relation 1",
      "sickNum": "0000198",
      "residenceNo": "0000198005",
      "nthHospital": 1,
      "actualHospitalDays": 25,
      "admissionTime": "2022-09-25 01:37:48",
      "admissionDepartment": "Adm. Department",
      "admissionWard": "Adm. Ward",
      "hospitalAdmission": "Hospital admission number",
      "operationLevel": "1,2,3,4",
      "operationType": "T1,T2,T3,T4",
      "operationSurgeon": "Surgeon1,Surgeon2,Surgeon3,Surgeon4",
      "operationCode": "Code1,Code 2,Code 3,Code 4",
      "operationName": "OpName1,OpName2,OpName3,OpName4",
      "operationDate": "2022-09-26,2022-09-27,2022-09-28,2022-09-29",
      "operationName1": "p1_n1,p2_n1",
      "operationName2": "p1_n2,p2_n2",
      "operationName3": ",p2_n3,p3_n3",
      "operationName4": ",,p3_n4,p4_n4",
      "drugAllergy": "Penicilin",
      "doctor": "Doctor name",
      "responsibleNurse": "Mary Responsible",
      "dischargeTime": "2022-09-30 23:52:36",
      "dischargeDepartment": "Disch Dept",
      "dischargeWard": "Disch Ward",
      "dischargeDiseaseCode": "Disch Disease Code",
      "dischargeMainDiagnosis": "Disch Main Diag",
      "otherDiseaseCodes": "Other disease codes",
      "dischargeOtherDiagnoses": "Disch other diag",
      "dischargeSituation": "Disch Situation",
      "dischargeInstructions": "Disch Instructions",
      "Hospitalized": "Hosp. Info",
      "note": "Notes of the episode"
    }';
        $sample2 = '{
      "sickId": "19680312",
      "sickNum": "SickNum 1",
      "name": "PCI Test patient",
      "sex": "女",
      "birthDate": "1968-03-12",
      "age": 54,
      "currentAddress": "Camino del Discharge 99",
      "nation": "中国",
      "identityNumber": "510212194412094125",
      "phone": "13594125051",
      "contactName": "Associate name 1",
      "contactPhone": "Associate phone 1",
      "relation": "Associate relation 1",
      "sickNum": "0000198",
      "residenceNo": "0000198006",
      "nthHospital": 2,
      "actualHospitalDays": 3,
      "admissionTime": "2022-10-01 01:37:48",
      "admissionDepartment": "Adm. Department",
      "admissionWard": "Adm. Ward",
      "hospitalAdmission": "Hospital admission number",
      "operationLevel": "99",
      "operationType": "T1",
      "operationSurgeon": "Cirujano A",
      "operationCode": "APC001",
      "operationName": "Apendicitis",
      "operationDate": "2022-10-02",
      "operationName2": "Extracción del apéndice",
      "drugAllergy": "Penicilin",
      "doctor": "El que opera las apendicitis",
      "responsibleNurse": "Jenny Responsible",
      "dischargeTime": "2022-10-03 23:52:36",
      "dischargeDepartment": "Disch Dept",
      "dischargeWard": "Disch Ward",
      "dischargeDiseaseCode": "Disch Disease Code",
      "dischargeMainDiagnosis": "Disch Main Diag",
      "otherDiseaseCodes": "Other disease codes",
      "dischargeOtherDiagnoses": "Disch other diag",
      "dischargeSituation": "Disch Situation",
      "dischargeInstructions": "Disch Instructions",
      "Hospitalized": "Hosp. Info",
      "note": "Se recupera sin problemas"
    }';
        return [KangxinPatientInfo::fromJson(json_decode($sample1)), KangxinPatientInfo::fromJson(json_decode($sample2))];
    }
}