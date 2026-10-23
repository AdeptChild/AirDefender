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

    "useFPPLocation" => [
        "type" => "bool",
        "default" => true,
        "label" => "Use FPP Location"
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

    "altitudeThresholdFt" => [
        "type" => "int",
        "default" => 3000,
        "label" => "Altitude Threshold (ft)"
    ],

    "fetchInterval" => [
        "type" => "int",
        "default" => 10,
        "label" => "ADS-B Fetch Interval (seconds)"
    ],

    "adsbSourceUrl" => [
        "type" => "string",
        "default" => "",
        "label" => "ADS-B JSON URL"
    ],

    "controllerType" => [
        "type" => "string",
        "default" => "falcon",
        "label" => "Controller Type"
    ],

    "serialPort" => [
        "type" => "string",
        "default" => "",
        "label" => "Serial Port"
    ],

    "dmxOutputs" => [
        "type" => "string",
        "default" => "",
        "label" => "DMX Outputs (comma-separated)"
    ]
];

?>
