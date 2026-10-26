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
<p>For each head (up to 8): turn it on, enter its <b>shutter channel</b> (the FPP channel number, the same
one used in your sequence), and the <b>closed value</b> (0&ndash;255) that closes the shutter, from the
fixture's manual. While Safety Mode is on, that channel is held at the closed value over whatever the
sequence is doing.</p>

<h3>Location</h3>
<p><b>Use FPP Location</b> uses the latitude and longitude set in FPP's own settings. Turn it off to enter
a different location.</p>

<h3>Safety Thresholds</h3>
<ul>
    <li><b>Distance Threshold (NM)</b>: how close, in nautical miles, an aircraft must be.</li>
    <li><b>Altitude Threshold (ft)</b>: how low, in feet, it must be.</li>
    <li><b>ADS-B Fetch Interval</b>: how often, in seconds, to ask for new flight data (5 or more).</li>
</ul>

<h3>ADS-B Feed</h3>
<p>The flight-data service to ask. airplanes.live, adsb.lol and adsb.fi are free online services and are
sent your show's location. The local receiver options read an ADS-B receiver running on this FPP, and
send nothing off the device.</p>
