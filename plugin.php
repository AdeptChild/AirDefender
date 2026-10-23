<?php

require_once("AirDefender.php");

/*
 * Plugin Info
 */
function AirDefender_PluginInfo() {
    return [
        "name"        => "AirDefender",
        "displayName" => "Air Defender",
        "author"      => "G Hancox",
        "description" => "ADS-B aircraft monitoring and DMX safety automation plugin for FPP."
    ];
}

/*
 * Called when plugin is installed
 */
function AirDefender_Setup() {
}

/*
 * Called when FPPD starts
 */
function AirDefender_Start() {
}

/*
 * Called when FPPD stops
 */
function AirDefender_Stop() {
}

/*
 * Page Router
 */
function AirDefender_Page($page) {
    include($page);
}

/*
 * Menu Loader
 */
function AirDefender_Menu() {
    global $plugin, $menu;

    $plugin = "AirDefender";
    $menu   = "content";

    include("menu.inc");
}

?>
