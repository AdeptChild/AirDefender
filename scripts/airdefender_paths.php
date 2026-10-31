<?php
/*
 * Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
 * Licensed under the GNU GPL v2; see LICENSE.
 *
 * Where FPP keeps things, found the way FPP provides instead of typed in, so
 * a relocated media folder still works:
 *   - FPP's web pages have $settings['mediaDirectory'] and ['logDirectory'];
 *   - the detection loop is given MEDIADIR and LOGDIR by airdefender_start.sh, which
 *     reads them from FPP's scripts/common;
 *   - anything else reads the media folder FPP records in media_root.txt.
 * /home/fpp/media is only the last resort.
 */

if (!function_exists('airdefender_media_dir')) {

    /* A folder from FPP's own $settings, when this runs inside FPP's web pages. */
    function airdefender_fpp_dir_setting(string $key): ?string
    {
        $s = $GLOBALS['settings'] ?? null;
        return is_array($s) && isset($s[$key]) && is_string($s[$key]) && $s[$key] !== ''
            ? rtrim($s[$key], '/') : null;
    }

    function airdefender_media_dir(): string
    {
        static $dir = null;
        if ($dir === null) {
            $fppDir = getenv('FPPDIR') ?: '/opt/fpp';
            $dir = airdefender_fpp_dir_setting('mediaDirectory')
                ?? (getenv('MEDIADIR') ?: null)
                ?? (trim((string) @file_get_contents($fppDir . '/www/media_root.txt')) ?: null)
                ?? '/home/fpp/media';
            $dir = rtrim($dir, '/');
        }
        return $dir;
    }

    function airdefender_log_dir(): string
    {
        return airdefender_fpp_dir_setting('logDirectory')
            ?? (getenv('LOGDIR') ? rtrim((string) getenv('LOGDIR'), '/') : null)
            ?? airdefender_media_dir() . '/logs';
    }

    /* Air Defender's one log, named the way FPP's plugin guidelines ask. */
    function airdefender_log_file(): string
    {
        return airdefender_log_dir() . '/plugin-AirDefender.log';
    }

    /* Air Defender's settings (not secrets: those are in airdefender_plugindata_dir()). */
    function airdefender_config_file(): string
    {
        return airdefender_media_dir() . '/config/plugin.AirDefender';
    }

    /* FPP's own settings file (location, time zone). */
    function airdefender_fpp_settings_file(): string
    {
        return (getenv('SETTINGSFILE') ?: null) ?? airdefender_media_dir() . '/settings';
    }

    /* Where secrets live (the master's UI password), mode 0600, outside config/. */
    function airdefender_plugindata_dir(): string
    {
        return airdefender_media_dir() . '/plugindata/AirDefender';
    }

    /* Read a key=value file such as FPP's settings or the plugin's config. */
    function airdefender_read_kv_file(string $path): array
    {
        $out = [];
        foreach ((array) @file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $parts = explode('=', (string) $line, 2);
            if (count($parts) === 2) {
                $out[trim($parts[0])] = trim(trim($parts[1]), "\"' ");
            }
        }
        return $out;
    }

    /*
     * Air Defender's role from FPP's own Player Mode: 'remote' on an FPP in
     * Remote mode, otherwise 'master' (shown as Player). It is only the
     * default: the role saved in Settings wins.
     */
    function airdefender_fpp_role(): string
    {
        $mode = strtolower((string) (airdefender_read_kv_file(airdefender_fpp_settings_file())['fppMode'] ?? ''));
        return $mode === 'remote' ? 'remote' : 'master';
    }

    /* Append a timestamped line to the log. */
    function airdefender_write_log(string $message): void
    {
        @file_put_contents(airdefender_log_file(), "[" . date("Y-m-d H:i:s") . "] {$message}\n", FILE_APPEND | LOCK_EX);
    }
}
