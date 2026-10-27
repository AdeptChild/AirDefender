<?php
/*
 * "Only for these playlists": the playlists Air Defender checks for aircraft
 * during, when When to Check is Scheduled shows or When playing. Saved in the
 * config as checkPlaylists, each name URL-encoded and joined with commas (a
 * playlist name may contain spaces, commas or quotes). Empty means every
 * playlist, as before this setting existed.
 */

require_once(__DIR__ . "/airdefender_paths.php");

/* The playlists in FPP, by name, sorted. */
function airdefender_playlist_names(): array
{
    $names = [];
    foreach (glob(airdefender_media_dir() . '/playlists/*.json') ?: [] as $file) {
        $names[] = basename($file, '.json');
    }
    natcasesort($names);
    return array_values($names);
}

function airdefender_encode_playlists(array $names): string
{
    $names = array_values(array_unique(array_filter(array_map('strval', $names), 'strlen')));
    return implode(',', array_map('rawurlencode', $names));
}

function airdefender_decode_playlists(string $saved): array
{
    $out = [];
    foreach (explode(',', $saved) as $part) {
        $name = rawurldecode(trim($part));
        if ($name !== '') {
            $out[] = $name;
        }
    }
    return array_values(array_unique($out));
}

/* Whether a playlist counts: every playlist does when the list is empty. */
function airdefender_playlist_selected(string $name, array $only): bool
{
    return $only === [] || in_array($name, $only, true);
}
