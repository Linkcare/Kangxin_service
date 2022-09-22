<?php
error_reporting(E_ERROR); // Do not report warnings to avoid undesired characters in output stream

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Content-type: application/json');
    $action = $_POST['do'];
} else {
    $action = $_GET['do'];
}

$logger = ServiceLogger::init($GLOBALS['LOG_LEVEL'], $GLOBALS['LOG_DIR']);
try {
    // Connect as service user, reusing existing session if possible
    apiConnect(null, $GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 47, $GLOBALS['SERVICE_TEAM'], true);

    switch ($action) {
        case 'import_patients' :
            $logger->trace('IMPORTING PATIENTS FROM KANGXIN');
            $service = new ServiceFunctions(LinkcareSoapAPI::getInstance(), KangxinAPI::getInstance());
            $res = $service->importPatients();
            $errorMessages = $res['errors'];

            $logger->trace('Successfully imported: ' . $res['success']);
            $logger->trace('Failed: ' . $res['failed']);
            if (!empty($errorMessages)) {
                $logger->error('IMPORT FINISHED WITH ERRORS', 1);
                foreach ($errorMessages as $msg) {
                    $logger->error($msg, 2);
                }
            }

            break;
    }
} catch (APIException $e) {
    $logger->error($e->getMessage());
} catch (Exception $e) {
    $logger->error($e->getMessage());
}

echo "";

