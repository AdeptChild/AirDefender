<h2>Air Defender Settings</h2>

<p>The page is in five sections, in the order you would set things up. When you change anything, a bar appears at
the bottom with <b>Save settings</b> and <b>Discard</b>. Changes take effect within a second of saving; FPP does not
need a restart.</p>

<h3>1. Air Defender</h3>
<ul>
    <li><b>Enabled</b>: turns aircraft checking on or off. It is off on a new install until you switch it on and
        save. A test from the dashboard still works while it is off.</li>
    <li><b>Role</b>: <b>Master</b> checks for aircraft and closes its own heads. Use it on a single FPP, and on the FPP
        that runs the show; it also covers heads it sends to over DDP or E1.31, such as a Falcon controller or an FPP in
        bridge mode. <b>Remote</b> is for an FPP in remote (multisync) mode, which plays its own copy of the sequence:
        it follows the master, including the master's tests, and closes its own heads. If it cannot reach the master
        for 5 seconds it closes its heads until the master is back. Install Air Defender on every Remote with heads.</li>
    <li><b>Master FPP address</b> (Remote only): leave blank to find the master automatically from FPP's MultiSync
        list. Enter its IP address or hostname if there is more than one player FPP on the network.</li>
    <li><b>Master's UI password</b> (Remote only): needed only if the master has an FPP UI password. It is kept in
        its own file in FPP's plugindata folder that only the fpp user can read, and never shown; leave it blank to keep the saved one, or tick <b>Remove saved password</b> to clear it.</li>
</ul>
<p>On a Remote, sections 3 to 5 are hidden: they are set on the master.</p>

<h3>2. Moving heads</h3>
<p>One row per head (up to 8). <b>+ Add head</b> adds a row; <b>&times;</b> removes one.</p>
<ul>
    <li><b>On</b>: whether Air Defender protects this head.</li>
    <li><b>Name</b> (optional): what to call the head in the log and on the dashboard. Picking a model fills it in.</li>
    <li><b>Model</b>: the head's model from FPP's model list, or <b>Manual channel</b>. To get your heads into the
        list, upload from xLights with FPP Connect with the <b>Models</b> column set to <b>All</b> (<b>Local</b> leaves
        out heads on other controllers, such as a Falcon), or add them on FPP's Pixel Overlay Models page. xLights
        changes spaces in model names to underscores.</li>
    <li><b>Start ch.</b>: for a manual head, the head's first FPP channel, its Start Channel in xLights (for example
        82448). For a model it is filled in from the model.</li>
    <li><b>Shutter ch.</b>: the shutter's channel number <i>within the fixture</i>, from its DMX chart (for example 7).</li>
    <li><b>Closed</b>: the value (0&ndash;255) that closes the shutter, from the fixture's DMX chart.</li>
</ul>
<p>The end of each row shows the FPP channel Air Defender worked out (start + shutter &minus; 1, so 82448 and 7 give
82454). While Safety Mode is on, that channel is held at the closed value over whatever the sequence is doing.
Heads picked by model are looked up by name, so a head re-patched in xLights and uploaded again is followed. If a
model is renamed or deleted, the row turns red and Air Defender keeps protecting the channel it last found for it
until you pick the model again.</p>

<h3>3. When and where</h3>
<ul>
    <li><b>When to check</b>: <b>Scheduled shows</b> (the default) checks only during scheduled shows, starting 2
        minutes before each one. <b>When playing</b> also covers playlists started by hand. <b>Always</b> checks around
        the clock. Outside those times the dashboard shows <b>Standby</b>, nothing is sent to the flight-data service,
        and the shutters are left to the show. To test a sequence at another time, use <b>Check now</b> on the
        dashboard (or the Air Defender: Check Now command): it checks for the time you pick, then turns itself off.</li>
    <li><b>Only these playlists</b> (Scheduled shows and When playing): tick the playlists that use your moving heads,
        and Air Defender checks only while one of them is playing or about to start; the rest (a late-night static show,
        say) leave it on Standby. None ticked means every playlist. A sequence started on its own is not part of a
        playlist, so with any ticked it is not checked: use <b>Check now</b>. A ticked playlist FPP no longer has is
        marked "not in FPP" until you untick it. If you use Remote Falcon, tick its playlist too: viewer requests play
        from it. (During a ticked scheduled show Air Defender keeps checking through requests anyway, since they play
        inside that show's schedule slot.)</li>
    <li><b>Use FPP's location</b>: uses the latitude and longitude in FPP's own settings. Turn it off to enter a
        different location. <b>Check it on a map</b> opens the spot in OpenStreetMap: always check that the pin is on
        your show, not just your town. FPP's location is often rough and can be miles off, and every distance (and the
        show elevation) is measured from this point. To enter your own, right-click your yard in Google Maps and click
        the numbers at the top of the menu to copy them.</li>
    <li><b>Show elevation</b>: your ground's height above sea level, in feet. Aircraft report their height above sea
        level, so Air Defender subtracts this to make the altitude limit count from your show. <b>Look it up</b> fills
        it in for the location above (FPP's or the one you entered), from Open-Meteo's elevation data (Copernicus DEM);
        that sends the location to api.open-meteo.com once, only when you press it. You can also type it in from a map
        or your phone. Left at 0, heights are measured above sea level, as before.</li>
</ul>

<h3>4. Safety limits</h3>
<ul>
    <li><b>Distance</b> (nautical miles) and <b>Altitude</b> (feet): heads close when an aircraft is inside the
        distance <i>and</i> below the altitude. With a <b>Show elevation</b> set, the altitude is measured above your
        show, and the hint shows what that is above sea level. The kilometres and metres are shown for reference.</li>
    <li><b>Hold after it leaves</b>: how long, in seconds, heads stay closed after the last aircraft leaves
        (0&ndash;600, default 30), so one on the edge of the area does not flash them on and off.</li>
    <li><b>Check every</b>: how often, in seconds, to ask for new flight data (5 or more). The estimate below it shows
        how many requests that makes, which matters because free services limit how many they answer. A smaller
        distance may want a quicker check.</li>
</ul>

<h3>5. Flight data</h3>
<ul>
    <li><b>Service</b>: <b>adsb.fi</b> (the default) and <b>adsb.lol</b> are free community services for personal,
        non-commercial use, and are sent your show's location. The local receiver options read an ADS-B receiver
        running on this FPP and send nothing off the device. (airplanes.live is no longer offered: its API now refuses
        requests without an account. A saved airplanes.live setting is switched to adsb.fi.)</li>
    <li><b>Use the other as a backup</b>: off until you turn it on, because it means the other service is also sent
        your show's location. When on, if adsb.fi or adsb.lol does not answer, Air Defender asks the other in the same
        check. It tries your chosen service again every minute and goes back to it when it
        answers. A local receiver never falls back to an online service.</li>
    <li><b>Test connection</b>: asks the service once, right away, using the location and limits on the page (saved or
        not), and shows how many aircraft it sees, the nearest one, how many are inside your limits, or the exact
        error.</li>
    <li><b>Close heads if feed is down</b>: off (the default), if the service stops answering, heads stay as they are
        unless an aircraft was near when it stopped. On, heads close whenever it stops answering, until it answers
        again: stricter, but an internet outage keeps them closed.</li>
</ul>
