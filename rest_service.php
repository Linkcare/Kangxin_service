<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream
const SERVICE_STATUS_IDLE = 'idle';
const SERVICE_STATUS_SUCCESS = 'success';
const SERVICE_STATUS_ERROR = 'error';

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    return;
}

// Response is always returned as JSON
header('Content-type: application/json');

$action = $_GET['function'];
$responseCode = SERVICE_STATUS_IDLE;
$responseMessage = '';

$logger = ServiceLogger::init($GLOBALS['LOG_LEVEL'], $GLOBALS['LOG_DIR']);
$connectionSuccessful = false;
try {
    // Connect as service user, reusing existing session if possible
    apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM'], true);
    $connectionSuccessful = true;
} catch (Exception $e) {
    $responseCode = SERVICE_STATUS_ERROR;
    $responseMessage = 'Error connecting with the service user credentials: ' . $e->getMessage();
    $logger->error($responseMessage);
}

if ($connectionSuccessful) {
    try {
        switch ($action) {
            case 'import_patients' :
                $logger->trace('IMPORTING PATIENTS FROM KANGXIN');
                $service = new ServiceFunctions(LinkcareSoapAPI::getInstance(), KangxinAPI::getInstance());
                $res = $service->importPatients();
                $errorMessages = $res['errors'];

                $success = $res['success'];
                $failed = $res['failed'];
                $responseMessage = 'Patient import result: Success: ' . $success . ', Failed: ' . $failed;
                if ($success + $failed == 0) {
                    $responseCode = SERVICE_STATUS_IDLE;
                } elseif ($failed > 0) {
                    $responseCode = SERVICE_STATUS_ERROR;
                } else {
                    $responseCode = SERVICE_STATUS_SUCCESS;
                }

                $logger->trace('Successfully imported: ' . $res['success']);
                $logger->trace('Failed: ' . $res['failed']);
                if (!empty($errorMessages)) {
                    $responseCode = SERVICE_STATUS_ERROR;
                    $logger->error('IMPORT FINISHED WITH ERRORS', 1);
                    foreach ($errorMessages as $msg) {
                        $logger->error($msg, 2);
                    }
                }
                break;
            default :
                $responseCode = SERVICE_STATUS_ERROR;
                $responseMessage = 'function "' . $action . '" not implemented';
                break;
        }
    } catch (APIException $e) {
        $responseCode = SERVICE_STATUS_ERROR;
        $responseMessage = $e->getMessage();
        $logger->error($responseMessage);
    } catch (Exception $e) {
        $responseCode = SERVICE_STATUS_ERROR;
        $responseMessage = $e->getMessage();
        $logger->error($responseMessage);
    }
}

$serviceResponse = new stdClass();
$serviceResponse->code = $responseCode;
$serviceResponse->message = $responseMessage;
if (!empty($errorMessages)) {
    foreach ($errorMessages as $errMsg) {
        $serviceResponse->error_details[] = $errMsg;
    }
}
echo json_encode($serviceResponse);

