<?php
/* The installed Air Defender version, from the VERSION file. */
function airdefender_version(): string
{
    $v = trim((string) @file_get_contents(dirname(__DIR__) . "/VERSION"));
    return $v !== '' ? $v : 'unknown';
}
