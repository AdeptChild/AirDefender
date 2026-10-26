<?php
/*
 * AirDefender output control.
 *
 * Safety mode closes each enabled moving head's shutter by putting an FPP
 * overlay on its shutter channel: PUT /overlays/range/<ch> {"Value": closed}.
 * fppd writes that value into the channel data on every output frame, over
 * whatever the sequence is doing, and it reaches the fixture however the
 * channel is output (a Kulp's serial DMX port, E1.31/DDP to a Falcon, ...).
 *
 * Clearing deletes the overlay, so the sequence drives the shutter again.
 * It deliberately does not force the shutter open: the show may want it
 * closed at that moment.
 *
 * The signal is never cut. Most fixtures hold their last values when DMX
 * stops, which could leave a beam frozen open and pointing up.
 *
 * Overlays live in fppd's memory and vanish when fppd restarts, so the loop
 * re-applies them every cycle while safety mode is on.
 */

class AirDefenderOutputs
{
    // fppd's API through FPP's own /api proxy (Apache), the documented route
    // for plugins; requests from the FPP itself need no UI password.
    const FPPD_API = 'http://127.0.0.1/api';

    /* Channels this object has overlaid, channel => value. */
    protected $applied = [];

    /* Last error from fppd, or '' if the last call went through. */
    public $lastError = '';

    public function __construct(array $previouslyApplied = [])
    {
        // Channels a previous run of the loop left overlaid, so they can be
        // released even if their head has since been disabled or changed.
        foreach ($previouslyApplied as $ch => $value) {
            $this->applied[(int) $ch] = (int) $value;
        }
    }

    /*
     * Make the outputs match $safe. $heads is channel => closed value.
     * Returns true if fppd accepted every request.
     */
    public function apply(bool $safe, array $heads): bool
    {
        $ok = true;
        $this->lastError = '';

        // Release anything overlaid that should not be: everything when
        // clear, or a channel no longer configured while safe.
        foreach (array_keys($this->applied) as $ch) {
            if (!$safe || !array_key_exists($ch, $heads)) {
                if ($this->release($ch)) {
                    unset($this->applied[$ch]);
                } else {
                    $ok = false;
                }
            }
        }

        if ($safe) {
            // Re-sent every cycle on purpose: it puts the overlay back after an
            // fppd restart, and costs one small local request per head.
            foreach ($heads as $ch => $value) {
                if ($this->hold($ch, $value)) {
                    $this->applied[$ch] = $value;
                } else {
                    $ok = false;
                }
            }
        }

        return $ok;
    }

    public function applied(): array
    {
        return $this->applied;
    }

    protected function hold(int $channel, int $value): bool
    {
        return $this->put("/overlays/range/{$channel}", ['Value' => $value]);
    }

    protected function release(int $channel): bool
    {
        return $this->put("/overlays/range/{$channel}", ['delete' => true]);
    }

    protected function put(string $path, array $body): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(self::FPPD_API . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }

        if ($response === false || $status !== 200) {
            $this->lastError = $status
                ? "fppd answered {$status} for {$path}: " . trim((string) $response)
                : "Could not reach fppd through " . self::FPPD_API . " ({$path})";
            return false;
        }

        return true;
    }
}
