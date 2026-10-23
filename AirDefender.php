<?php

require_once("/opt/fpp/www/common.php");
require_once("/opt/fpp/www/plugin.php");

class AirDefender extends FPPPlugin {

    public function __construct() {
        $this->pluginName = "AirDefender";
        $this->pluginVersion = "0.2.0";
        $this->pluginDescription = "Monitors ADS-B aircraft and disables DMX outputs when aircraft are nearby.";
    }

    public function Init() {
        $this->Log("Air Defender initialized.");
    }

    public function DeInit() {
        $this->Log("Air Defender stopped.");
    }

    private function Log($msg) {
        $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
        file_put_contents("/home/fpp/media/logs/airdefender.log", $line, FILE_APPEND);
    }
}

?>






