<?php
session_start();

/*
 * CUSTOMIZABLE CONFIGURATION VARIABLES
 * To override the default values defined below, create a file named conf/configuration.php in the service root directory and replace the value of the
 * desired variables by a custom value
 */
$GLOBALS['LANG'] = 'EN';
$GLOBALS['DEFAULT_TIMEZONE'] = 'Asia/Shanghai';
/* Log level. Possible values: debug,trace,warning,error,none */
$GLOBALS['LOG_LEVEL'] = 'error';
/* Directory to store logs in disk. If null, logs will only be generated on stdout */
$GLOBALS['LOG_DIR'] = null;

/* Url of the Linkcare API */
$GLOBALS['WS_LINK'] = 'https://dev-api.linkcareapp.com/ServerWSDL.php';

/* Credentials of the SERVICE USER that will connect to the Linkare platform and import the patients */
$GLOBALS['SERVICE_USER'] = 'service';
$GLOBALS['SERVICE_PASSWORD'] = 'password';
$GLOBALS['SERVICE_TEAM'] = 'LINKCARE';

/* Endpoint URL of the Kangxin API */
$GLOBALS['KANGXIN_API_URL'] = 'http://183.230.182.185:6050/dmp/phm';

/* Number of patients that should be requested to Kangxin and imported in one iteration of the import process */
$GLOBALS['PATIENT_BATCH_SIZE'] = 1;

/* Program and Team codes of the Subscription where the admissions of the patients will be created */
$GLOBALS['PROGRAM_CODE'] = 'xxxxx';
$GLOBALS['TEAM_CODE'] = 'xxxxx';

/*
 * The Patient Identifier is not globally unique. It is only unique in a particular Hospital.
 * The following configuration variable defines the Team for which the Patient Identifier (the Kangxin Hospital Team)
 */
$GLOBALS['PATIENT_IDENTIFIER_TEAM'] = 'LINKCARE';

/* LOAD CUSTOMIZED CONFIGURATION */
if (file_exists(__DIR__ . '/../conf/configuration.php')) {
    include_once __DIR__ . '/../conf/configuration.php';
}

/*
 * INTERNAL CONFIGURATION VARIABLES (not customizable)
 */
require_once 'classes/ServiceLogger.php';
require_once 'classes/ErrorCodes.php';
require_once 'classes/ServiceException.php';
require_once 'classes/KangxinPatientInfo.php';
require_once 'classes/KangxinAPI.php';
require_once 'utils.php';
require_once 'WSAPI/WSAPI.php';
require_once 'classes/ServiceFunctions.php';
require_once 'functions.php';

date_default_timezone_set($GLOBALS['DEFAULT_TIMEZONE']);

// INTERNAL NAME IN LINKCARE PLATFORM OF THE IDENTIFIERS OF THE PATIENT
/* Name of the Linkcare IDENTIFIER to store the National Id Card number of the patients (globally unique) */
$GLOBALS['NATIONAL_IDENTIFIER'] = 'NAT_ZH';

/*
 * Name of the Linkcare IDENTIFIER to store the Patient Id. Patient identifiers are not globally unique. They are only unique in an specific Team
 * (tipically a Hospital)
 */
$GLOBALS['PATIENT_IDENTIFIER'] = 'PARTICIPANT_REF';


