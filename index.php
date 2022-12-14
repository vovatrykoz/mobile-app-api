<?php

require "./inc/config.php";
require "./inc/bootstrap.php";
 
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );
 
require "./controllers/DefaultController.php";

$objFeedController = new DefaultController();
$strMethodName = $uri[3];
$objFeedController->{$strMethodName}();

?>