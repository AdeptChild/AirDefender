<?php
/*
 * Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
 * Licensed under the GNU GPL v2; see LICENSE.
 *
 * The master's FPP UI password, which a Remote sends when it asks the master
 * for its status. FPP's plugin guidelines keep credentials out of config/
 * (FPP's backups and crash reports copy that folder), so it lives in
 * plugindata/AirDefender/master-password, readable only by its owner (0600).
 * Versions before 0.10.0 kept it in config/plugin.AirDefender as
 * masterPassword = "b64:...": that is moved here the first time it is read.
 */

require_once(__DIR__ . "/airdefender_paths.php");

function airdefender_master_password_file(): string
{
    return airdefender_plugindata_dir() . '/master-password';
}

/* The saved password, or '' when there is none. */
function airdefender_master_password(): string
{
    $file = airdefender_master_password_file();
    if (is_file($file)) {
        return (string) @file_get_contents($file);
    }

    // One saved by an older version: move it out of config/.
    $old = (string) (airdefender_read_kv_file(airdefender_config_file())['masterPassword'] ?? '');
    if ($old === '') {
        return '';
    }
    $plain = strpos($old, 'b64:') === 0 ? (string) base64_decode(substr($old, 4)) : $old;
    if (airdefender_set_master_password($plain)) {
        airdefender_remove_config_key('masterPassword');
    }
    return $plain;
}

/* Save the password ('' removes it). Returns false if it could not be written. */
function airdefender_set_master_password(string $password): bool
{
    $file = airdefender_master_password_file();
    if ($password === '') {
        @unlink($file);
        return !file_exists($file);
    }

    $dir = dirname($file);
    if (!is_dir(dirname($dir))) {
        @mkdir(dirname($dir), 0755, true);       // FPP's plugindata folder
    }
    if (!is_dir($dir) && !@mkdir($dir, 0700)) {
        return false;
    }
    @chmod($dir, 0700);

    $tmp = $file . '.tmp';
    $mask = umask(077);
    $ok = @file_put_contents($tmp, $password, LOCK_EX) !== false && @chmod($tmp, 0600) && @rename($tmp, $file);
    umask($mask);
    if (!$ok) {
        @unlink($tmp);
    }
    return $ok;
}

/* Remove one key from Air Defender's config file, leaving the rest as it is. */
function airdefender_remove_config_key(string $key): void
{
    $path = airdefender_config_file();
    $fh = @fopen($path, 'c+');
    if (!$fh) {
        return;
    }
    if (flock($fh, LOCK_EX)) {
        $kept = [];
        $removed = false;
        while (($line = fgets($fh)) !== false) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) === $key) {
                $removed = true;
                continue;
            }
            $kept[] = $line;
        }
        if ($removed) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, implode('', $kept));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}
