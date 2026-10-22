<?php

// Air Defender - ADS-B Aircraft Safety Plugin for FPP
// Author: Gabe
// Version: 0.2.0

require_once("/opt/fpp/www/common.php");

class AirDefender extends FPPPlugin
{
    public function __construct()
    {
        $this->pluginName = "AirDefender";
        $this->pluginVersion = "0.2.0";
        $this->pluginDescription = "Monitors ADS-B aircraft and disables DMX outputs when aircraft are nearby.";
    }

    // Called when plugin is enabled
    public function Init()
    {
        $this->Log("Air Defender initialized.");
    }

    // Called when plugin is disabled
    public function DeInit()
    {
        $this->Log("Air Defender stopped.");
    }

    // Main loop - runs continuously
    public function Run()
    {
        $settings = $this->LoadSettings();

        if (!$settings['enabled']) {
            $this->Log("Plugin disabled, skipping loop.");
            return;
        }

        $this->Log("Starting ADS-B check loop...");

        while (true) {
            $this->CheckAircraft($settings);
            sleep($settings['fetchInterval']);
        }
    }

    // Load settings from AirDefender.settings.php
    private function LoadSettings()
    {
        return [
            "enabled"          => $this->GetSetting("enabled"),
            "safetyMode"       => $this->GetSetting("safetyMode"),
            "overrideLocation" => $this->GetSetting("overrideLocation"),
            "latitude"         => $this->GetSetting("latitude"),
            "longitude"        => $this->GetSetting("longitude"),
            "distanceNM"       => intval($this->GetSetting("distanceNM")),
            "fetchInterval"    => intval($this->GetSetting("fetchInterval")),
            "dmxOutputs"       => $this->ParseOutputs($this->GetSetting("dmxOutputs"))
        ];
    }

    // Convert comma-separated DMX outputs into an array
    private function ParseOutputs($list)
    {
        $arr = array_map('trim', explode(",", $list));
        return array_filter($arr, fn($v) => $v !== "");
    }

    // Main aircraft check logic
    private function CheckAircraft($settings)
    {
        $location = $this->GetLocation($settings);

        if (!$location) {
            $this->Log("No valid location available.");
            return;
        }

        $aircraft = $this->FetchAircraft();

        if (!$aircraft || !isset($aircraft['aircraft'])) {
            $this->Log("No aircraft data received.");
            return;
        }

        foreach ($aircraft['aircraft'] as $plane) {
            if (!isset($plane['lat']) || !isset($plane['lon'])) continue;

            $dist = $this->DistanceNM(
                $location['lat'],
                $location['lon'],
                $plane['lat'],
                $plane['lon']
            );

            if ($dist <= $settings['distanceNM']) {
                $this->Log("Aircraft within {$dist} NM — triggering DMX safety shutdown.");
                $this->DisableDMX($settings['dmxOutputs']);
                return;
            }
        }

        // No aircraft nearby — restore DMX
        $this->EnableDMX($settings['dmxOutputs']);
    }

    // Fetch ADS-B aircraft from ADSBexchange
    private function FetchAircraft()
    {
        $url = "https://adsbexchange.com/api/aircraft/json/";

        $this->Log("Fetching ADS-B data...");

        $json = @file_get_contents($url);

        if (!$json) {
            $this->Log("Failed to fetch ADS-B data.");
            return null;
        }

        return json_decode($json, true);
    }

    // Get location from FPP or override
    private function GetLocation($settings)
    {
        if ($settings['overrideLocation']) {
            return [
                "lat" => floatval($settings['latitude']),
                "lon" => floatval($settings['longitude'])
            ];
        }

        $sys = json_decode(file_get_contents("/home/fpp/media/config/system.json"), true);

        if (!isset($sys['Latitude']) || !isset($sys['Longitude'])) {
            return null;
        }

        return [
            "lat" => floatval($sys['Latitude']),
            "lon" => floatval($sys['Longitude'])
        ];
    }

    // Haversine distance in nautical miles
    private function DistanceNM($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadiusNM = 3440.065;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat/2)**2 + cos($lat1) * cos($lat2) * sin($dlon/2)**2;
        $c = 2 * asin(sqrt($a));

        return $earthRadiusNM * $c;
    }

    // Disable DMX outputs
    private function DisableDMX($outputs)
    {
        foreach ($outputs as $out) {
            $cmd = "fpp -d $out 0";
            exec($cmd);
        }
    }

    // Enable DMX outputs
    private function EnableDMX($outputs)
    {
        foreach ($outputs as $out) {
            $cmd = "fpp -d $out 255";
            exec($cmd);
        }
    }

    // Logging helper
    private function Log($msg)
    {
        $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . "\n";
        file_put_contents("/home/fpp/media/logs/airdefender.log", $line, FILE_APPEND);
    }
}

$plugin = new AirDefender();
$plugin->Run();


