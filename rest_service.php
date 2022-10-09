<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream

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

$serviceResponse = new ServiceResponse(ServiceResponse::IDLE, 'Request received for action: ' . $action);

$logger = ServiceLogger::init($GLOBALS['LOG_LEVEL'], $GLOBALS['LOG_DIR']);
$connectionSuccessful = false;
try {
    Database::connect($GLOBALS['INTEGRATION_DBSERVER'], $GLOBALS['INTEGRATION_DATABASE'], $GLOBALS['INTEGRATION_DBUSER'],
            $GLOBALS['INTEGRATION_DBPASSWORD']);

    // Connect as service user, reusing existing session if possible
    apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM'], true);
    $connectionSuccessful = true;
} catch (Exception $e) {
    $serviceResponse->setCode(ServiceResponse::ERROR);
    $serviceResponse->setMessage('Error initializing service: ' . $e->getMessage());
    $logger->error($serviceResponse->getMessage());
    exit(1);
}

$processHistory = new ProcessHistory($action);
$processHistory->save();

if ($connectionSuccessful) {
    try {
        switch ($action) {
            case 'import_patients' :
                $logger->trace('CREATION ADMISSIONS IN CARE PLAN');
                $service = new ServiceFunctions(LinkcareSoapAPI::getInstance(), KangxinAPI::getInstance());
                $serviceResponse = $service->importPatients($processHistory);
                break;
            case 'import_patients' :
                $logger->trace('IMPORTING PATIENT RECORDS FROM KANGXIN');
                $fromRecord = $_GET['from'];
                $service = new ServiceFunctions(LinkcareSoapAPI::getInstance(), KangxinAPI::getInstance());
                $serviceResponse = $service->fetchKangxinRecords($processHistory, $fromRecord);
                break;
            default :
                $serviceResponse->setCode(ServiceResponse::ERROR);
                $serviceResponse->setMessage('function "' . $action . '" not implemented');
                break;
        }
    } catch (Exception $e) {
        $serviceResponse->setCode(ServiceResponse::ERROR);
        $serviceResponse->setMessage($e->getMessage());
    }
}

if ($serviceResponse->getCode() == ServiceResponse::ERROR) {
    $processHistory->setStatus(ProcessHistory::STATUS_FAILURE);
    $logger->error($serviceResponse->getMessage());
} else {
    $processHistory->setStatus(ProcessHistory::STATUS_SUCCESS);
    $logger->trace($serviceResponse->getMessage());
    $details = $processHistory->getLogs();
    foreach ($details as $msg) {
        $logger->error($msg->getMessage(), 2);
    }
}
$processHistory->setOutputMessage($serviceResponse->getMessage());
$processHistory->setEndDate(currentDate());
$processHistory->save();

echo $serviceResponse->toString();
