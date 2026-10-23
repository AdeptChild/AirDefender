<?php

class AirDefenderController {
    protected $type;
    protected $serialPort;
    protected $dmxOutputs;

    public function __construct($type = 'falcon', $serialPort = '', $dmxOutputs = '') {
        $this->type = strtolower((string) $type);
        $this->serialPort = (string) $serialPort;
        $this->dmxOutputs = $dmxOutputs;
    }

    public function activateSafetyMode() {
        $this->log("Safety mode ON");
    }

    public function clearSafetyMode() {
        $this->log("Safety mode OFF");
    }

    protected function log($message) {
        $logFile = "/home/fpp/media/logs/airdefender.log";
        $timestamp = date("Y-m-d H:i:s");
        $line = "[{$timestamp}] {$message}\n";

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

class FalconController extends AirDefenderController {
    public function activateSafetyMode() {
        $this->log("Falcon controller safety mode ON");
    }

    public function clearSafetyMode() {
        $this->log("Falcon controller safety mode OFF");
    }
}

class KulpController extends AirDefenderController {
    public function activateSafetyMode() {
        $this->log("Kulp controller safety mode ON");
    }

    public function clearSafetyMode() {
        $this->log("Kulp controller safety mode OFF");
    }
}

function airdefender_make_controller($type, $serialPort, $dmxOutputs) {
    $type = strtolower((string) $type);

    if ($type === 'kulp') {
        return new KulpController($type, $serialPort, $dmxOutputs);
    }

    if ($type === 'falcon') {
        return new FalconController($type, $serialPort, $dmxOutputs);
    }

    return new AirDefenderController($type, $serialPort, $dmxOutputs);
}
