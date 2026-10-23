<?php
$menuEntries = Array(
    Array(
        'text' => 'Air Defender',
        'type' => 'status',
        'page' => 'status.php',
        'wrap' => 1
    )
);

foreach ($menuEntries as $entry)
{
    if ($entry['type'] != $menu)
        continue;

    printf("<li><a href='plugin.php?plugin=%s&page=%s'>%s</a></li>\n",
        $plugin, $entry['page'], $entry['text']);
}
?>
