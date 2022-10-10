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
$GLOBALS['KANGXIN_API_URL'] = 'http://kangxin_api';
$GLOBALS['KANGXIN_API_TIMEOUT'] = 30;

/*
 * Maximum number of patients that should be imported to the Linkcare platform in one execution. 0 means no limit (continue while there are records to
 * process)
 */
$GLOBALS['PATIENT_MAX'] = 0;
/* Number of patients that will be requested to the Kangxin API in each request */
$GLOBALS['PATIENT_PAGE_SIZE'] = 50;

/* Program and Team codes of the Subscription where the admissions of the patients will be created */
$GLOBALS['PROGRAM_CODE'] = 'xxxxx';
$GLOBALS['TEAM_CODE'] = 'xxxxx';

/*
 * The Patient Identifier is not globally unique. It is only unique in a particular Hospital.
 * The following configuration variable defines the Team for which the Patient Identifier (the Kangxin Hospital Team)
 */
$GLOBALS['PATIENT_IDENTIFIER_TEAM'] = 'LINKCARE';

/*
 * Database credentials
 */
$GLOBALS['INTEGRATION_DATABASE'] = 'linkcare';
$GLOBALS['INTEGRATION_DBSERVER'] = 'xxx.linkcareapp.com';
$GLOBALS['INTEGRATION_DBUSER'] = 'KANGXIN_INTEGRATION';
$GLOBALS['INTEGRATION_DBPASSWORD'] = 'yyy';
$GLOBALS['SYSDBA_DBUSER'] = '';
$GLOBALS['SYS_DBPASSWORD'] = '';

// Time between requests to the KANGXIN API to avoid blocking the server
$GLOBALS['KANGXIN_REQUEST_DELAY'] = 5;

/* LOAD CUSTOMIZED CONFIGURATION */
if (file_exists(__DIR__ . '/../conf/configuration.php')) {
    include_once __DIR__ . '/../conf/configuration.php';
}

/*
 * INTERNAL CONFIGURATION VARIABLES (not customizable)
 */
require_once 'classes/Database.php';
require_once 'classes/ServiceLogger.php';
require_once 'classes/ErrorCodes.php';
require_once 'classes/ServiceException.php';
require_once 'classes/KangxinItemCodes.php';
require_once 'classes/KangxinProcedure.php';
require_once 'classes/KangxinDiagnosis.php';
require_once 'classes/KangxinPatientInfo.php';
require_once 'classes/KangxinAPI.php';
require_once 'classes/ProcessHistory.php';
require_once 'classes/RecordPool.php';
require_once 'utils.php';
require_once 'WSAPI/WSAPI.php';
require_once 'classes/ServiceResponse.php';
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


