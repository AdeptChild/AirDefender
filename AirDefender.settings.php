<?php

$settings = [
    "enabled" => [
        "type" => "bool",
        "default" => false,
        "label" => "Enable Air Defender"
    ],

    "safetyMode" => [
        "type" => "bool",
        "default" => false,
        "label" => "Enable Safety Mode"
    ],

    "overrideLocation" => [
        "type" => "bool",
        "default" => false,
        "label" => "Override Location"
    ],

    "latitude" => [
        "type" => "string",
        "default" => "",
        "label" => "Latitude"
    ],

    "longitude" => [
        "type" => "string",
        "default" => "",
        "label" => "Longitude"
    ],

    "distanceNM" => [
        "type" => "int",
        "default" => 5,
        "label" => "Distance Threshold (NM)"
    ],

    "fetchInterval" => [
        "type" => "int",
        "default" => 10,
        "label" => "ADS-B Fetch Interval (seconds)"
    ],

    "dmxOutputs" => [
        "type" => "string",
        "default" => "",
        "label" => "DMX Outputs (comma-separated)"
    ]
];

?>
