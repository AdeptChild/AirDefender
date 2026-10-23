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
        $this->sendControllerCommand('ON');
    }

    public function clearSafetyMode() {
        $this->log("Safety mode OFF");
        $this->sendControllerCommand('OFF');
    }

    protected function sendControllerCommand($state) {
        $serialPort = trim($this->serialPort);
        $command = "{$this->type}:state={$state}"
            . ($serialPort !== '' ? ":port={$serialPort}" : '')
            . ($this->dmxOutputs !== '' ? ":outputs={$this->dmxOutputs}" : '');

        $markerFile = "/home/fpp/media/plugins/AirDefender/controller_state.txt";
        @file_put_contents($markerFile, $command . PHP_EOL, LOCK_EX);

        if ($serialPort !== '') {
            $this->log("Controller adapter queued command for serial port {$serialPort}: {$command}");
        }
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
        $this->sendControllerCommand('ON');
    }

    public function clearSafetyMode() {
        $this->log("Falcon controller safety mode OFF");
        $this->sendControllerCommand('OFF');
    }
}

class KulpController extends AirDefenderController {
    public function activateSafetyMode() {
        $this->log("Kulp controller safety mode ON");
        $this->sendControllerCommand('ON');
    }

    public function clearSafetyMode() {
        $this->log("Kulp controller safety mode OFF");
        $this->sendControllerCommand('OFF');
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
