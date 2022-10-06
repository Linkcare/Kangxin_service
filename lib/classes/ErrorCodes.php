<?php

class ErrorCodes {
    /** @var string Error trying to communicate with API */
    const API_COMM_ERROR = 'COMM_ERROR';

    /** @var string API function responded with an error status */
    const API_ERROR_STATUS = 'API_ERROR_STATUS';

    /** @var string The response returned by the API does not have the expected format */
    const API_INVALID_DATA_FORMAT = 'API_INVALID_DATA_FORMAT';

    /** @var string The API function returned an error message */
    const API_FUNCTION_ERROR = 'API_FUNCTION_ERROR';

    /** @var string Generic error */
    const UNEXPECTED_ERROR = 'UNEXPECTED_ERROR';
}