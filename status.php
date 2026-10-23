<?php
require_once("/opt/fpp/www/common.php");

echo "<div style='padding:20px;background:#222;color:#fff;border-radius:8px;'>";
echo "<h2>Air Defender Debug Output</h2>";
echo "<p>STATUS.PHP IS RUNNING</p>";
echo "</div>";

include("/home/fpp/media/plugins/AirDefender/www/index.php");

echo "<div style='padding:20px;background:#333;color:#fff;border-radius:8px;margin-top:20px;'>";
echo "<p>INDEX.PHP INCLUDED</p>";
echo "</div>";
?>

