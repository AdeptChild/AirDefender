<h2>Air Defender Settings</h2>

<p>Changes take effect within a second of pressing <b>Save Settings</b>; FPP does not need a restart.</p>

<h3>Plugin Status</h3>
<p><b>Enable Air Defender</b> turns aircraft checking on or off. A test from the dashboard still works
while it is off.</p>

<h3>Operation Mode</h3>
<ul>
    <li><b>Master</b>: checks for aircraft and closes its own heads. Use this on a single FPP, and on the
        FPP that runs the show. It also covers heads the master sends to over DDP or E1.31, such as a
        Falcon controller or an FPP in bridge mode.</li>
    <li><b>Remote</b>: for an FPP in remote (multisync) mode, which plays its own copy of the sequence.
        It follows the master, including the master's tests, and closes its own heads. If it cannot reach
        the master for 5 seconds it closes its heads until the master is back. Install Air Defender on
        every Remote that has heads.</li>
    <li><b>Master FPP Address</b> (Remote only): leave blank to find the master automatically from FPP's
        MultiSync list. Enter its IP address or hostname if there is more than one player FPP on the
        network.</li>
</ul>

<h3>Moving Heads</h3>
<p>For each head (up to 8), turn it on and tell Air Defender where its shutter is:</p>
<ul>
    <li><b>Name</b> (optional): what to call the head in the log, for example "Left mover". Picking a model
        fills it in with the model's name.</li>
    <li><b>Model</b>: pick the head's model from FPP's model list, or choose <b>Manual channel</b>. To get your
        heads into the list, upload from xLights with FPP Connect with the <b>Models</b> column set to
        <b>All</b> (<b>Local</b> leaves out heads on other controllers, such as a Falcon), or add them on FPP's
        Pixel Overlay Models page. xLights changes spaces in model names to underscores.</li>
    <li><b>Start Ch.</b>: for a manual head, the head's first FPP channel: its Start Channel in xLights (for
        example 82448). For a model it is filled in from the model.</li>
    <li><b>Shutter Ch.</b>: the shutter's channel number <i>within the fixture</i>, from its DMX chart (for
        example 7).</li>
    <li><b>Closed</b>: the value (0&ndash;255) that closes the shutter, from the fixture's DMX chart.</li>
</ul>
<p>Air Defender works out the FPP channel (start + shutter &minus; 1, so 82448 and 7 give 82454) and shows it at
the end of the row. While Safety Mode is on, that channel is held at the closed value over whatever the sequence
is doing. Heads picked by model are looked up by name, so if you re-patch a head in xLights and upload again, Air
Defender follows it. If a model is renamed or deleted, the row turns red and Air Defender keeps protecting the
channel it last found for it until you pick the model again.</p>

<h3>Location</h3>
<p><b>Use FPP Location</b> uses the latitude and longitude set in FPP's own settings. Turn it off to enter
a different location.</p>

<h3>Safety Thresholds</h3>
<ul>
    <li><b>Distance Threshold (NM)</b>: how close, in nautical miles, an aircraft must be.</li>
    <li><b>Altitude Threshold (ft)</b>: how low, in feet, it must be.</li>
    <li><b>ADS-B Fetch Interval</b>: how often, in seconds, to ask for new flight data (5 or more).</li>
    <li><b>Hold After Aircraft Leaves</b>: how long, in seconds, heads stay closed after the last aircraft leaves
        (0&ndash;600, default 30), so one on the edge of the area does not flash them on and off.</li>
</ul>

<h3>ADS-B Feed</h3>
<p>The flight-data service to ask. airplanes.live, adsb.lol and adsb.fi are free online services and are
sent your show's location. The local receiver options read an ADS-B receiver running on this FPP, and
send nothing off the device.</p>
<p><b>Close Heads If Feed Is Down</b>: off (the default), if the service stops answering, heads stay as they are
unless an aircraft was near when it stopped. On, heads close whenever it stops answering, until it answers again:
stricter, but an internet outage keeps them closed.</p>
