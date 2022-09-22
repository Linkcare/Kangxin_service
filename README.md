# Kangxin Service
Service for integrating the Kangxin hospital HIS with the Linkcare platform.
This project implements several functions as a REST API.

The base URL for invoking the published functions is:
  https://deploy_url/rest_service.php
  
Each function can be invoked appending a parameter "do" to specify the required function.
Example:
  https://base_url/rest_service.php?do=import_patients
  
## Service configuration
The file /lib/default_conf.php provides a default configuration.
To customize the configuration create a new file under the directory /conf (at root directory level) called "configuration.php". The default_conf.php contains explanation for the variables that can be customized.
  

## REST services (GET http method)
- import_patients (GET method): Sends a request to Kangxin hospital to fetch patients that should be imported in the Linkcare platform. A new Admission is created for each patient in the configured Care Plan