<h2>Air Defender Dashboard</h2>

<p>Air Defender watches for low aircraft near your show using live ADS-B flight data, and closes your
moving heads' shutters while one is close. This page shows what it is doing right now and updates by
itself every couple of seconds.</p>

<h3>Status tiles</h3>
<ul>
    <li><b>Plugin Enabled</b>: whether Air Defender is switched on in Settings.</li>
    <li><b>Safety Mode</b>: <b>Active</b> means the shutters of the heads you enabled are being held
        closed. <b>Active (Test)</b> means a test is running. <b>Inactive</b> means the show controls the
        shutters as normal.</li>
    <li><b>Mode</b>: Master or Remote (see Settings).</li>
    <li><b>Distance / Altitude Threshold</b>: an aircraft must be inside the distance <i>and</i> below the
        altitude to turn Safety Mode on.</li>
    <li><b>ADS-B Feed</b>: the flight-data service being checked.</li>
</ul>

<h3>When Safety Mode turns on</h3>
<ul>
    <li>An aircraft is inside both thresholds (aircraft on the ground are ignored).</li>
    <li>For 30 seconds after it leaves, so a plane on the edge of the area does not flash the lights.</li>
    <li>If the flight-data service stops answering while an aircraft was near, until it answers again.</li>
    <li>On a Remote: whenever its master is in Safety Mode, and whenever it cannot reach the master for
        5 seconds.</li>
</ul>
<p>When Safety Mode turns off, the shutters go back to whatever the sequence is doing; they are not forced
open.</p>

<h3>Test Mode</h3>
<p>Pick a length (5 seconds to 30 minutes) and press <b>Start Test</b> to close the shutters for that long,
so you can go outside and watch the heads go dark and come back. The countdown keeps going if you refresh or
open the page somewhere else, and <b>Stop Test</b> ends it early. A test started on the master also closes
the heads on every Remote. You can also start and stop a test from a playlist, schedule or command preset
with the <b>Air Defender: Start Test</b> and <b>Air Defender: Stop Test</b> commands.</p>

<h3>Last Event</h3>
<p>The newest line of the Air Defender log, <code>/home/fpp/media/logs/airdefender.log</code>, in this
FPP's time zone. The full log (under <b>Content Setup &rarr; File Manager &rarr; Logs</b>) explains every
change, including why Safety Mode turned on and any problem reaching the flight-data service or the
master.</p>
