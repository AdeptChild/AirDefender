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
    // Setup tasks if needed
}

/*
 * Called when FPPD starts
 */
function AirDefender_Start() {
    // Startup tasks if needed
}

/*
 * Called when FPPD stops
 */
function AirDefender_Stop() {
    // Shutdown tasks if needed
}

/*
 * Page Router
 * FPP calls this when loading plugin pages
 */
function AirDefender_Page($page) {
    include($page);
}

/*
 * Menu Loader
 * FPP calls this to build the plugin menu
 */
function AirDefender_Menu() {
    global $plugin, $menu;

    // These MUST match pluginInfo.json and folder name
    $plugin = "AirDefender";
    $menu   = "content";

    include("menu.inc");
}
?>
