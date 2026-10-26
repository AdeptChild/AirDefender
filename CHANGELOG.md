# Air Defender changelog

## 0.9.4 (2026-10-26)

- New **Show elevation** setting: aircraft report their height above sea level, so Air Defender now subtracts your
  ground's elevation and the altitude limit means height above your show. **Look it up** fills it in from Open-Meteo
  for the location in use (FPP's or your own). Left at 0, nothing changes.
- The dashboard, radar, Test connection, log and command-line check show heights above the show once it is set.

## 0.9.3 (2026-10-26)

Fixes for a fresh install:
- Settings showed Air Defender as Enabled before anything was saved, while it was actually off (the dashboard said Disabled). It now starts off everywhere until you switch it on, so nothing is sent anywhere before then.
- Settings help now shows adsb.fi as the default service, and explains the backup option and the Remote's master password.
- A head typed into the empty first row was saved as Off unless its switch was turned on too. An empty row now switches itself on when you fill it in; a head you switch off on purpose stays off.

## 0.9.2 (2026-10-26)

- New radar-scope icon (shown on the plugin's card in FPP).
- The dashboard and settings use the icon's colours: radar greens, with orange for aircraft and safety, blue for tests, and red and yellow for problems.
- The dashboard's Sky radar is drawn like the icon, with a turning sweep (still for anyone whose system asks for reduced motion) and the distance limit marked inside the rim.

## 0.9.1 (2026-10-26)

- While on the backup flight-data service, the chosen one is now tried again every minute (was every 5 minutes), and Air Defender switches back as soon as it answers.
- The dashboard names both services while on the backup, for example "adsb.fi (backup: adsb.lol not answering)", so it is clear which one is chosen in Settings.

## 0.9.0 (2026-10-26)

First complete version, ready for live testing.

### Protection
- Closes each moving head's shutter with an FPP channel overlay while an aircraft is inside the distance and below the altitude limit, and hands the shutter back to the sequence afterwards. The DMX signal is never cut, since most fixtures hold their last values without it.
- Works for heads on FPP's own outputs (such as a Kulp's DMX port) and heads the master sends to over DDP or E1.31 (such as a Falcon).
- Holds the heads closed for a while after the aircraft leaves (default 30 seconds), and keeps them closed if the flight-data service stops answering while an aircraft was near.
- Optional "Close heads if feed is down" for stricter protection.
- Re-applies the shutter overlays every second, so they survive an fppd restart.
- A watchdog restarts the detection loop within seconds if it ever stops.

### When it checks
- Checks only during scheduled shows by default, starting 2 minutes before each one; or whenever FPP is playing; or always.
- "Check now" on the dashboard (or the Air Defender: Check Now command) checks outside show times for a set time, then turns itself off.

### Heads
- Pick each head by its model (uploaded from xLights by FPP Connect with Models set to All), or enter its start channel by hand; then the shutter's channel in the fixture and its closed value. Air Defender works out the FPP channel.
- Follows a head re-patched in xLights, and keeps protecting a head whose model was renamed or deleted.

### Master and Remote
- A Remote follows its master, including the master's tests, and closes its own heads if it loses the master for 5 seconds.
- Finds the master automatically, or uses an address you enter; supports a master with an FPP UI password.

### Flight data
- adsb.fi (default) and adsb.lol, each the other's automatic backup, or a local ADS-B receiver (never backed up by an online service).
- Test connection on the settings page.

### Dashboard and settings
- Dashboard with a status banner, Lite and Detailed views, a Sky radar, the heads' state, a test with countdown, and Last Event (safety on/off and tests only).
- Settings page in five sections with live hints and an unsaved-changes bar.
- FPP commands: Air Defender: Start Test, Stop Test and Check Now.
- The log keeps 7 days and can be cleared from the dashboard. Times follow FPP's time zone.
