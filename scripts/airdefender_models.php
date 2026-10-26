<?php
/*
 * Moving heads by model name.
 *
 * Each head is either:
 *   - a model from FPP's model list (uploaded from xLights by FPP Connect, or
 *     added on FPP's Pixel Overlay Models page) plus the shutter's channel
 *     number within the fixture, from its DMX chart; or
 *   - "Manual": the head's start channel typed in by hand (from xLights),
 *     plus the same shutter channel within the fixture.
 *
 * A model is looked up by name every time, so a head re-patched in xLights
 * and re-uploaded is followed with no change here. If the model can no
 * longer be found (renamed or deleted), the head keeps using the channel it
 * last resolved to, so it is never left unprotected, and a warning says so.
 *
 * Settings per head N: headNEnabled, headNName (optional label), headNModel
 * ('' = manual), headNStart (manual: the head's first FPP channel), headNOffset
 * (the shutter's channel within the fixture, from 1), headNChannel (the
 * channel it resolves to, saved by the settings page; on settings from before
 * headNStart existed, the manual absolute channel), headNClosedValue.
 */

const AIRDEFENDER_HEADS = 8;
const AIRDEFENDER_MAX_CHANNEL = 8388608;   // FPPD_MAX_CHANNELS in FPP's Sequence.h

/*
 * FPP's models as name => ['start' => first channel (1-based), 'count' => channels],
 * sorted by name, or null if the list could not be read at all.
 */
function airdefender_fpp_models(): ?array
{
    $list = null;

    // fppd's live list.
    $context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $body = @file_get_contents('http://127.0.0.1:32322/models', false, $context);
    $json = $body !== false ? json_decode($body, true) : null;
    if (is_array($json) && ($json === [] || array_keys($json) === range(0, count($json) - 1))) {
        $list = $json;
    }

    // fppd not running: the file it loads them from.
    if ($list === null) {
        $file = json_decode((string) @file_get_contents('/home/fpp/media/config/model-overlays.json'), true);
        if (is_array($file) && isset($file['models']) && is_array($file['models'])) {
            $list = $file['models'];
        }
    }

    if ($list === null) {
        return null;
    }

    $models = [];
    foreach ($list as $m) {
        if (!is_array($m) || !isset($m['Name']) || !is_string($m['Name']) || $m['Name'] === '') {
            continue;
        }
        $start = (int) ($m['StartChannel'] ?? 0);
        $count = (int) ($m['ChannelCount'] ?? 0);
        if ($start < 1 || $count < 1) {
            continue;   // framebuffer and other models with no channels of their own
        }
        $models[$m['Name']] = ['start' => $start, 'count' => $count];
    }
    uksort($models, 'strnatcasecmp');
    return $models;
}

/*
 * Resolve one head. Returns:
 *   enabled  bool
 *   label    the head's name, or "MH<n>" if it has none
 *   model    model name, or '' for manual
 *   channel  absolute FPP channel to hold, or null if there is none
 *   closed   closed value 0-255
 *   status   'manual' | 'ok' | 'missing' | 'bad-offset' | 'unchecked' | 'unset'
 *   message  plain-language problem, or ''
 *
 * $models is the list from airdefender_fpp_models(), or null when it could
 * not be read. $lastKnown is the channel this head last resolved to, if known.
 */
function airdefender_resolve_head(array $settings, int $n, ?array $models, ?int $lastKnown = null): array
{
    $truthy = in_array(strtolower(trim((string) ($settings["head{$n}Enabled"] ?? '0'))), ['1', 'true', 'yes', 'on', 'y'], true);
    $name = trim((string) ($settings["head{$n}Name"] ?? ''));
    $label = $name !== '' ? $name : "MH{$n}";
    $model = trim((string) ($settings["head{$n}Model"] ?? ''));
    $offset = (int) ($settings["head{$n}Offset"] ?? 1);
    $start = (int) ($settings["head{$n}Start"] ?? 0);
    $saved = (int) ($settings["head{$n}Channel"] ?? 0);
    $closed = max(0, min(255, (int) ($settings["head{$n}ClosedValue"] ?? 0)));
    $fallback = $lastKnown !== null && $lastKnown > 0 ? $lastKnown : ($saved > 0 ? $saved : null);

    $head = ['enabled' => $truthy, 'label' => $label, 'model' => $model, 'channel' => null, 'closed' => $closed,
             'status' => 'unset', 'message' => ''];

    if ($model === '') {
        if ($start > 0) {
            // The head's start channel plus the shutter's channel within the fixture.
            if ($offset < 1) {
                $head['message'] = "{$label}: enter the shutter's channel in the fixture (1 or more)";
                return $head;
            }
            $channel = $start + $offset - 1;
        } elseif ($saved > 0) {
            // Settings from before Start existed: an absolute shutter channel.
            $channel = $saved;
        } else {
            $head['message'] = "{$label} has no start channel set";
            return $head;
        }
        if ($channel > AIRDEFENDER_MAX_CHANNEL) {
            $head['message'] = "{$label}: channel {$channel} is past FPP's last channel (" . AIRDEFENDER_MAX_CHANNEL . ")";
            return $head;
        }
        $head['channel'] = $channel;
        $head['status'] = 'manual';
        return $head;
    }

    if ($models === null) {
        // Could not read FPP's models at all: keep protecting the last channel.
        $head['channel'] = $fallback;
        $head['status'] = 'unchecked';
        return $head;
    }

    if (!isset($models[$model])) {
        $head['channel'] = $fallback;
        $head['status'] = 'missing';
        $head['message'] = "{$label}: model \"{$model}\" was not found in FPP (renamed or deleted?)"
            . ($fallback ? "; still protecting channel {$fallback}, its last known shutter channel" : "; nothing to protect")
            . ". Pick it again in Air Defender settings";
        return $head;
    }

    $m = $models[$model];
    if ($offset < 1 || $offset > $m['count']) {
        $head['channel'] = $fallback;
        $head['status'] = 'bad-offset';
        $head['message'] = "{$label}: shutter channel {$offset} is outside \"{$model}\", which has {$m['count']} channel(s)"
            . ($fallback ? "; still protecting channel {$fallback}" : "");
        return $head;
    }

    $head['channel'] = $m['start'] + $offset - 1;
    $head['status'] = 'ok';
    return $head;
}

/* All heads, keyed 1..8. $lastKnown is head number => channel. */
function airdefender_resolve_heads(array $settings, ?array $models, array $lastKnown = []): array
{
    $heads = [];
    for ($n = 1; $n <= AIRDEFENDER_HEADS; $n++) {
        $heads[$n] = airdefender_resolve_head($settings, $n, $models, isset($lastKnown[$n]) ? (int) $lastKnown[$n] : null);
    }
    return $heads;
}
