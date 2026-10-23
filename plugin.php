<?php
require_once("AirDefender.php");

function AirDefender_PluginInfo() {
    return [
        "name" => "AirDefender",
        "displayName" => "Air Defender",
        "author": "G Hancox",
        "description" => "ADS-B aircraft monitoring and DMX safety automation plugin for FPP."
    ];
}

function AirDefender_Setup() {
    // Called when plugin is installed
}

function AirDefender_Start() {
    // Called when FPPD starts
}

function AirDefender_Stop() {
    // Called when FPPD stops
}

function AirDefender_Page($page) {
    include($page);
}
?>
