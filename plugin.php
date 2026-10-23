<?php

function AirDefender_PluginInfo() {
    return [
        "name"        => "AirDefender",
        "displayName" => "Air Defender",
        "author"      => "G Hancox",
        "description" => "ADS-B aircraft monitoring and DMX safety automation plugin for FPP."
    ];
}

function AirDefender_Setup() {}

function AirDefender_Start() {
    $script = "/home/fpp/media/plugins/AirDefender/scripts/airdefender_loop.php";
    if (file_exists($script)) {
        $cmd = "php {$script} >/dev/null 2>&1 &";
        shell_exec($cmd);
    }
}

function AirDefender_Stop() {
    shell_exec("pkill -f 'airdefender_loop.php' >/dev/null 2>&1 || true");
}

function AirDefender_Page($page) {
    include($page);
}

function AirDefender_Menu() {
    global $plugin, $menu;

    $plugin = "AirDefender";
    $menu   = "content";

    include("menu.inc");
}

?>
