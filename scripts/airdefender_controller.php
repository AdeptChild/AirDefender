<?php

class AirDefenderController {
    protected $type;
    protected $blackoutStartChannel;
    protected $blackoutEndChannel;

    public function __construct($type = 'falcon', $blackoutStartChannel = 0, $blackoutEndChannel = 0) {
        $this->type = strtolower((string) $type);
        $this->blackoutStartChannel = (int) $blackoutStartChannel;
        $this->blackoutEndChannel = (int) $blackoutEndChannel;
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
        $start = (int) $this->blackoutStartChannel;
        $end = (int) $this->blackoutEndChannel;

        $range = ($start > 0 && $end > 0 && $end >= $start)
            ? "{$start}-{$end}"
            : "unconfigured";

        $command = "{$this->type}:state={$state}:range={$range}";

        $markerFile = "/home/fpp/media/plugins/AirDefender/controller_state.txt";
        @file_put_contents($markerFile, $command . PHP_EOL, LOCK_EX);

        $this->log("Controller adapter queued command: {$command}");
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

function airdefender_make_controller($type, $blackoutStartChannel, $blackoutEndChannel) {
    $type = strtolower((string) $type);

    if ($type === 'kulp') {
        return new KulpController($type, $blackoutStartChannel, $blackoutEndChannel);
    }

    if ($type === 'falcon') {
        return new FalconController($type, $blackoutStartChannel, $blackoutEndChannel);
    }

    return new AirDefenderController($type, $blackoutStartChannel, $blackoutEndChannel);
}
