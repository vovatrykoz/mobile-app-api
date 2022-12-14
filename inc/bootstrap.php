<?php
define("PROJECT_ROOT_PATH", __DIR__);

//this file is neccessarry so that different parts of the API can talk to each other
//we are basically saying where everything is and specifying paths to different parts of the API

// include main configuration file
require_once PROJECT_ROOT_PATH . "\config.php";
 
// include the base controller file
require_once PROJECT_ROOT_PATH . "\..\controllers\baseController.php";
 
// include the use model file
require_once PROJECT_ROOT_PATH . "\..\models\userSetsModel.php";

?>
