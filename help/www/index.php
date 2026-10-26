<h2>Air Defender Dashboard</h2>

<p>Air Defender watches for low aircraft near your show using live ADS-B flight data, and closes your
moving heads' shutters while one is close. This page shows what it is doing right now and updates by
itself every couple of seconds.</p>

<h3>Lite and Detailed</h3>
<p>The switch at the top picks how much the dashboard shows. Your choice is remembered in this browser.</p>
<ul>
    <li><b>Lite</b> (default): the status banner, Test, a one-line summary of the heads, and the last event.
        Refreshes every 5 seconds.</li>
    <li><b>Detailed</b>: adds the <b>Sky</b> radar and every head with its channel. Refreshes every 2 seconds.</li>
</ul>
<p>Either way, the dashboard only asks FPP for anything while it is open; the detection loop does the same work
whether you look or not.</p>

<h3>Status banner</h3>
<ul>
    <li><b>All clear</b> (green): no aircraft inside your limits; the show controls the shutters.</li>
    <li><b>Safety active</b> (orange): the shutters are held closed, with the reason and when it started.</li>
    <li><b>Test running</b> (blue): a test is closing the heads, with the time left.</li>
    <li><b>Can't check the sky</b> (yellow): the flight-data service is not answering. See the reason, and the
        <b>Close Heads If Feed Is Down</b> setting.</li>
    <li><b>Standby</b> (grey-blue): no show is on, so Air Defender is not checking (see <b>When to Check</b> in
        Settings). It shows the next scheduled show; checks start 2 minutes before it.</li>
    <li><b>Disabled</b> (grey): Air Defender is switched off in Settings.</li>
    <li><b>Not running</b> (red): the detection loop is not running. Restart FPPD (or reboot).</li>
</ul>
<p>The chips on the right show whether this FPP is the Master or a Remote, and whether Air Defender is enabled.</p>
<p><b>Check now</b> (Master, when checks follow show times): checks for aircraft for 30 minutes to 4 hours even
with no show on, for example while testing a sequence, then turns itself off. The banner shows the time left and an
<b>End</b> button.</p>

<h3>Sky (Detailed)</h3>
<p>Your show is the blue dot in the middle, north is up, and the dashed orange ring is your distance limit.
Orange dots are aircraft inside your limits (close enough and low enough); grey dots are aircraft that are too
high or too far. Hover over a dot for its callsign, distance and height (above your show once <b>Show elevation</b> is set in
Settings). Aircraft on the ground are not shown.
On a Remote, the Sky card says it follows its master instead.</p>

<h3>Moving heads</h3>
<p>Each enabled head, its FPP channel, and whether Air Defender is holding its shutter <b>Closed</b> or the
<b>Show</b> controls it. A head with a problem (for example a model that was renamed) says so in red; in Lite
the summary line counts them.</p>

<h3>When Safety Mode turns on</h3>
<ul>
    <li>An aircraft is inside the distance and below the altitude (aircraft on the ground are ignored).</li>
    <li>For the <b>Hold after it leaves</b> time (30 seconds unless changed) after it leaves, so a plane on the
        edge of the area does not flash the lights.</li>
    <li>If the flight-data service stops answering while an aircraft was near, until it answers again. With
        <b>Close heads if feed is down</b> on, whenever it stops answering.</li>
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
<p>The most recent time Safety Mode turned on or off, or a test started or ended, in this FPP's time zone. Other
notes (a head re-patched, the flight-data service coming back, and so on) are only in the full log,
<code>/home/fpp/media/logs/airdefender.log</code>, under <b>Content Setup &rarr; File Manager &rarr; Logs</b>.</p>
<p>The log lists aircraft that passed near your show, so it keeps only the last 7 days. <b>Clear log</b> deletes it
now.</p>
