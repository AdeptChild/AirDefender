<?php
/*
 * Air Defender - Copyright (C) 2026 G Hancox. Created by G Hancox, built with Claude.
 * Licensed under the GNU GPL v2; see LICENSE.
 */
/* The installed Air Defender version, from the VERSION file. */
function airdefender_version(): string
{
    $v = trim((string) @file_get_contents(dirname(__DIR__) . "/VERSION"));
    return $v !== '' ? $v : 'unknown';
}
