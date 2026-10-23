<?php
require_once("/opt/fpp/www/common.php");
require_once("AirDefender.php");

$plugin = new AirDefender();
$plugin->RenderPage("www/index.php");
?>
