<?php

$pluginName = "AirDefender";

$settings = [
    "enabled" => [
        "type" => "bool",
        "default" => false,
        "label" => "Enable Air Defender",
        "description" => "Turn the Air Defender plugin on or off."
    ],

    "safetyMode" => [
        "type" => "bool",
        "default" => true,
        "label" => "Enable DMX Safety Mode",
        "description" => "Automatically disable DMX outputs when aircraft are nearby."
    ],

    "overrideLocation" => [
        "type" => "bool",
        "default" => false,
        "label" => "Override FPP Location",
        "description" => "Use custom latitude/longitude instead of FPP system location."
    ],

    "latitude" => [
        "type" => "text",
        "default" => "",
        "label" => "Latitude",
        "description" => "Custom latitude (only used if Override Location is enabled)."
    ],

    "longitude" => [
        "type" => "text",
        "default" => "",
        "label" => "Longitude",
        "description" => "Custom longitude (only used if Override Location is enabled)."
    ],

    "distanceNM" => [
        "type" => "int",
        "default" => 3,
        "label" => "Aircraft Distance Threshold (NM)",
        "description" => "DMX outputs will disable when aircraft are within this distance."
    ],

    "fetchInterval" => [
        "type" => "int",
        "default" => 5,
        "label" => "ADS-B Fetch Interval (seconds)",
        "description" => "How often Air Defender checks for nearby aircraft."
    ],

    "dmxOutputs" => [
        "type" => "text",
        "default" => "",
        "label" => "DMX Outputs to Disable",
        "description" => "Comma-separated list of DMX output numbers to disable when aircraft are nearby."
    ]
];

?>


