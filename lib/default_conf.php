<?php
session_start();

/*
 * CUSTOMIZABLE CONFIGURATION VARIABLES
 * To override the default values defined below, create a file named conf/configuration.php in the service root directory and replace the value of the
 * desired variables by a custom value
 */
/**
 * ** REQUIRED CONFIGURATION PARAMETERS ***
 */
/* Url of the Linkcare API */
$GLOBALS['WS_LINK'] = 'https://api.linkcareapp.com/ServerWSDL.php';

/* Credentials of the SERVICE USER that will connect to the Linkare platform and import the patients */
$GLOBALS['SERVICE_USER'] = 'service';
$GLOBALS['SERVICE_PASSWORD'] = 'password';
$GLOBALS['SERVICE_TEAM'] = 'LINKCARE';

/* Language of the "service" user. This will be the default language of new patients created by the service */
$GLOBALS['LANGUAGE'] = 'ZH';

/* Endpoint URL of the Kangxin API */
$GLOBALS['KANGXIN_API_URL'] = 'http://183.230.182.185:6050/dmp/phm';

/* Program code of the Subscription to store the information about the episodes received from Kangxin */
$GLOBALS['KANGXIN_EPISODES_PROGRAM_CODE'] = 'KANGXIN_ADMISSIONS';
/*
 * Program code of a Subscription of a secondary PROGRAM. When a new episode is received from Kangxin, a new ADMISSION will also be created in this
 * SUBSCRIPTION. The goal is to let a Case Manager decide whether the patient must be enrolled in any Post Intervention Follow-up PROGRAM
 */
$GLOBALS['DISCHARGE_FOLLOWUP_PROGRAM_CODE'] = 'PCI_DCH';
/* Team code of the Subscription owner */
$GLOBALS['TEAM_CODE'] = 'xxxxx';
/*
 * Date of the oldest procedure that will be requested to the Kangxin API. This value normally is only used during
 * the first load, because once the DB is feeded with an initial number of records, tha date of the last record will be
 * used in further requests to the Kangxin API to receive only incremental updates
 */
$GLOBALS['MINIMUM_DATE'] = '2022-10-01';

/*
 * Threshold date to discharge the Admission in the Linkcare platform. All records received with a date of discharge (in the Kangxin Hospital) older
 * that the specified in this parameter will be automatically discharged in the Linkcare platform
 */
$GLOBALS['DISCHARGE_DATE_THRESHOLD'] = '2022-10-01';

/*
 * The Patient Identifier is not globally unique. It is only unique in a particular Hospital.
 * The following configuration variable defines the Team for which the Patient Identifier (the Kangxin Hospital Team)
 */
$GLOBALS['PATIENT_IDENTIFIER_TEAM'] = 'KANGXIN';

/*
 * Database credentials
 */
// DB Credentials of a user with read/write privileges on the tables used by the service
$GLOBALS['INTEGRATION_DATABASE'] = 'linkcare';
$GLOBALS['INTEGRATION_DBSERVER'] = 'xxx.linkcareapp.com';
$GLOBALS['INTEGRATION_DBUSER'] = 'KANGXIN_INTEGRATION';
$GLOBALS['INTEGRATION_DBPASSWORD'] = 'yyy';
/*
 * DB Credentials of a user with administrative privileges for creating schemas and tables.
 * This credentials are necessary only for the initial deploy of the service, and can be removed later.
 */
$GLOBALS['ADMIN_DBUSER'] = '';
$GLOBALS['ADMIN_DBPASSWORD'] = '';

/**
 * ** OPTIONAL CONFIGURATION PARAMETERS ***
 */
/* Default timezone used by the service. It is used when it is necessary to generate dates in a specific timezone */
$GLOBALS['DEFAULT_TIMEZONE'] = 'Asia/Shanghai';
/* Log level. Possible values: debug,trace,warning,error,none */
$GLOBALS['LOG_LEVEL'] = 'error';
/* Directory to store logs in disk. If null, logs will only be generated on stdout */
$GLOBALS['LOG_DIR'] = null;
/*
 * Maximum number of patients that should be imported to the Linkcare platform in one execution. 0 means no limit (continue while there are records to
 * process)
 */
$GLOBALS['PATIENT_MAX'] = 10000;
/* Number of patients process */
$GLOBALS['PATIENT_PAGE_SIZE'] = 50;

/* Maximum time (in seconds) to wait for a response of the Kangxin API to consider that it is not responding and cancel the request */
$GLOBALS['KANGXIN_API_TIMEOUT'] = 300;
/* Time (in seconds) between successive requests to the Kangxin API to avoid blocking the server */
$GLOBALS['KANGXIN_REQUEST_DELAY'] = 5;

/*
 * The Kangxin API allows to fetch records based on its update time or operation time.
 * This parameter allows to configure how records should be requested
 */
$GLOBALS['USE_UPDATE_TIME_FILTER'] = true;

/*
 * If true, when an episode information changes respect to the last information in the PHM, a notification will be sent to the Case Manager indicating
 * that there are changes in the patient information
 */
$GLOBALS['INFORM_EPISODE_CHANGES'] = true;

/**
 * SIMULATION CONFIGURATION PARAMETERS
 */
/*
 * Indicate if the service will use simulated requests to the Kangxin API. If true, instead of calling the real API, fake date will be used (as if it
 * were returned by the API)
 */
$GLOBALS['SIMULATE_KANGXIN_API'] = false;
/* Parameter to anonymize patient data received from the API */
$GLOBALS['ANONYMIZE_DATA'] = false;

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
require_once 'classes/DeployFunctions.php';
require_once 'functions.php';

date_default_timezone_set($GLOBALS['DEFAULT_TIMEZONE']);

/*
 * Name of the Linkcare IDENTIFIER to store the Patient Id. Patient identifiers are not globally unique. They are only unique in an specific Team
 * (tipically a Hospital)
 */
$GLOBALS['PATIENT_IDENTIFIER'] = 'PARTICIPANT_REF';


