<?php

// Air Defender Plugin Settings File
// Defines all user-configurable settings shown in the FPP UI.

$pluginName = "AirDefender";

$settings = [
    // Master enable switch
    "enabled" => [
        "type"        => "bool",
        "default"     => false,
        "label"       => "Enable Air Defender",
        "description" => "Turn the Air Defender plugin on or off."
    ],

    // Safety mode toggle
    "safetyMode" => [
        "type"        => "bool",
        "default"     => true,
        "label"       => "Enable DMX Safety Mode",
        "description" => "Automatically disable DMX outputs when aircraft are nearby."
    ],

    // Override location toggle
    "overrideLocation" => [
        "type"        => "bool",
        "default"     => false,
        "label"       => "Override FPP Location",
        "description" => "Use custom latitude/longitude instead of FPP system location."
    ],

    // Latitude
    "latitude" => [
        "type"        => "text",
        "default"     => "",
        "label"       => "Latitude",
        "description" => "Custom latitude (only used if Override Location is enabled)."
    ],

    // Longitude
    "longitude" => [
        "type"        => "text",
        "default"     => "",
        "label"       => "Longitude",
        "description" => "Custom longitude (only used if Override Location is enabled)."
    ],

    // Distance threshold (nautical miles)
    "distanceNM" => [
        "type"        => "int",
        "default"     => 3,
        "label"       => "Aircraft Distance Threshold (NM)",
        "description" => "DMX outputs will disable when aircraft are within this distance."
    ],

    // ADS-B fetch interval
    "fetchInterval" => [
        "type"        => "int",
        "default"     => 5,
        "label"       => "ADS-B Fetch Interval (seconds)",
        "description" => "How often Air Defender checks for nearby aircraft."
    ],

    // DMX outputs to disable
    "dmxOutputs" => [
        "type"        => "text",
        "default"     => "",
        "label"       => "DMX Outputs to Disable",
        "description" => "Comma-separated list of DMX output numbers to disable when aircraft are nearby."
    ]
];


