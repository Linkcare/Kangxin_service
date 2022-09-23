# Kangxin Service
Service for integrating the Kangxin hospital HIS with the Linkcare platform.<br>
This project implements several functions published as a REST API. All functions must be invoked using POST method.<br>

The base URL for invoking the published functions is:<br>
- https://deploy_url/rest_service<br>

Published functions can be invoked appending the name of the required function to the base URL.<br>
Example:<br>
- https://base_url/rest_service/import_patients
  
## Service configuration
The file /lib/default_conf.php provides a default configuration.<br>
To customize the configuration create a new file under the directory /conf (at root directory level) called "configuration.php". The default_conf.php contains explanation for the variables that can be customized.<br>
  

## REST services
All functions return a JSON response (Content-type: application/json) with the following structure:<br>

 {<br>
   "status": "idle",<br>
   "message": "Informative message returned by the service"<br>
 }<br>
 
The possible response status are:
- idle: The function was executed but no work was done
- success: The function was executed successfully
- error: The function was executed with errors

### Published functions
- <b>import_patients</b>: Sends a request to Kangxin hospital to fetch patients that should be imported in the Linkcare platform. A new Admission is created for each patient in the configured Care Plan