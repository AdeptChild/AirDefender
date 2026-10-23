<?php
require_once("/opt/fpp/www/common.php");

echo "<div style='padding:20px;background:#222;color:#fff;border-radius:8px;'>";
echo "<h2>Air Defender Debug Output</h2>";
echo "<p>STATUS.PHP IS RUNNING</p>";
echo "</div>";

echo "<div style='padding:20px;background:#444;color:#fff;border-radius:8px;margin-top:20px;'>";
echo "<p>Attempting to include index.php...</p>";
echo "</div>";

$indexPath = "/home/fpp/media/plugins/AirDefender/www/index.php";

if (file_exists($indexPath)) {
    echo "<div style='padding:20px;background:#333;color:#fff;border-radius:8px;margin-top:20px;'>";
    echo "<p>index.php FOUND — including now...</p>";
    echo "</div>";

    include($indexPath);

    echo "<div style='padding:20px;background:#333;color:#fff;border-radius:8px;margin-top:20px;'>";
    echo "<p>index.php INCLUDED — if no content appeared, index.php output is empty or suppressed.</p>";
    echo "</div>";
} else {
    echo "<div style='padding:20px;background:#900;color:#fff;border-radius:8px;margin-top:20px;'>";
    echo "<p>ERROR: index.php NOT FOUND at:</p>";
    echo "<p>$indexPath</p>";
    echo "</div>";
}
?>


