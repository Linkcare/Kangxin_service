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
}