# Air Defender

**Moving-head safety for Falcon Player (FPP): closes the shutters while low aircraft fly near your show.**

Bright moving heads and beams pointed at the sky can dazzle pilots. Air Defender watches live ADS-B flight data
for your location, and while an aircraft is close enough and low enough, it holds your moving heads' shutters
closed. When the sky is clear again, it hands the shutters back to your sequence.

Version 1.2.0 (see [CHANGELOG.md](CHANGELOG.md)). For FPP 10 and later.

---

## How it protects your show

- While an aircraft is **inside your distance limit and below your altitude limit**, each enabled head's **shutter
  channel is held at its closed value** using an FPP channel overlay. FPP writes that value on every output frame,
  over whatever the sequence is doing, so it reaches the fixture however the channel is output: a Kulp's DMX port,
  a pixel cape, or E1.31/DDP to a Falcon or another controller.
- When the aircraft leaves, the heads stay closed for a short **hold** (30 seconds by default) so a plane on the edge
  of the area doesn't flash them on and off. Then the overlay is removed and **the sequence controls the shutter
  again**. Air Defender never forces a shutter open.
- **The DMX signal is never cut.** Most moving heads and lasers hold their last values when DMX stops, which could
  leave a beam frozen open, pointing up. Closing the shutter is the only way to know the fixture is dark.
- If the flight-data service stops answering while an aircraft was near, the heads stay closed until it answers
  again. (Turn on **Close heads if feed is down** to close them on any outage.)
- The overlays are re-applied every second, so they survive an fppd restart, and a watchdog restarts the detection
  loop within seconds if it ever stops.

## Install

**From FPP's Plugin Manager (recommended)**

1. In FPP, open **Content Setup → Plugin Manager**.
2. Paste this into the plugin info URL box and press **Retrieve Plugin Info**:

   ```
   https://raw.githubusercontent.com/G-Hancox/AirDefender/master/pluginInfo.json
   ```

3. Review the privacy summary, press **Install**, then restart FPPD when FPP asks.

**By hand (SSH)**

```bash
cd /home/fpp/media/plugins/
git clone https://github.com/G-Hancox/AirDefender.git
```

Then restart FPPD.

**Updating:** press **Update** for Air Defender in Plugin Manager. The detection loop restarts with the new code by
itself. **Release Notes** in Plugin Manager shows the changelog.

## Quick start

1. Open **Air Defender → Settings** (from the Content Setup menu, or the dashboard's Settings button).
2. **Air Defender:** switch it **On** (a new install starts off), with **Role: Master** (see
   [Master and Remote](#master-and-remote) if you have FPP remotes).
3. **Moving heads:** add each head. Pick its **model**, or choose **Manual channel** and enter its **start channel**
   from xLights. Then enter the **shutter channel** from the fixture's DMX chart (for example 7) and the **closed**
   value (often 0). The row shows the FPP channel it works out, for example `→ FPP ch 82454`.
4. **When and where:** press **Check it on a map** and make sure the pin is on **your show**, not just your town (see
   [Check your location](#check-your-location)). Then press **Look it up** beside **Show elevation** so heights are
   measured from your show.
5. **Flight data:** press **Test connection**. You should see `OK · N aircraft nearby`.
6. Save, open the **dashboard**, and run a **Test**: the heads should close within a second and open again when the
   timer ends.

Every page has help behind FPP's **?** button.

## Settings

| Section | What's there |
|---|---|
| **1. Air Defender** | Enabled; **Role** (Master or Remote); on a Remote, the master's address and UI password. |
| **2. Moving heads** | Up to 8 heads: name, model or manual start channel, shutter channel in the fixture, closed value. |
| **3. When and where** | **When to check** (all scheduled shows, or only selected playlists) with the playlist list, the show location and its elevation. |
| **4. Safety limits** | Distance (NM), altitude (ft), hold after the aircraft leaves, and how often to check. |
| **5. Flight data** | The service, **Test connection**, an optional backup service, and **Close heads if feed is down**. |

Changes take effect within a second of saving; no restart is needed.

### Picking heads by model

To list your heads by name, upload your models from xLights: in **FPP Connect**, set the **Models** column to
**All** for the FPP and upload. (**Local** only includes models on that FPP's own outputs, so it leaves out heads on
a Falcon or other controller.) You can also add models by hand on FPP's **Pixel Overlay Models** page. FPP does not
create models for DMX fixtures by itself, and xLights changes spaces in model names to underscores.

Heads picked by model are looked up by name, so a head re-patched in xLights and uploaded again is followed
automatically. If a model is renamed or deleted, its row turns red and Air Defender keeps protecting the channel it
last found for it until you pick the model again.

### Check your location

**Don't trust the location until you've seen it on a map.** FPP's own location (the default) is often set roughly,
or worked out from your internet connection, and can be miles from your yard. Everything Air Defender decides is
measured from this point: which aircraft are within your distance, and the ground elevation that heights are
measured from. If it's off by a few miles, planes right over your show can count as outside your limits.

1. Open **Settings → When and where** and press **Check it on a map**.
2. Zoom in: the pin should be on your property.
3. If it isn't, turn off **Use FPP's location** and enter your show's latitude and longitude. (In Google Maps,
   right-click your yard and click the numbers at the top of the menu to copy them.) Or fix the location in FPP's
   own settings.
4. Press **Look it up** again for **Show elevation**, so it matches the corrected spot, and save.

### Show elevation

Aircraft report their height above sea level, not above your yard. Set **Show elevation** (feet above sea level) and
Air Defender subtracts it, so the altitude limit means height above your show: with the show at 800 ft and a 3,000 ft
limit, heads close for aircraft below 3,800 ft above sea level. This matters most on high ground; in Denver, at
5,300 ft, a 3,000 ft limit measured from sea level would never trigger.

**Look it up** fills it in for whichever location is in use (FPP's, or the one you entered) from Open-Meteo's
elevation data (Copernicus DEM, 90 m). It sends that location to api.open-meteo.com once, only when you press it. Left
at 0, heights are measured above sea level. The dashboard, radar, log and command-line check all show heights above
the show once it is set.

### When it checks

Air Defender only checks while there's a show to protect. Outside those times it is on **Standby**: it sends nothing
to the flight-data service and leaves the shutters to the show. **When to check** has two choices:

- **All scheduled shows** (the default): checks during every scheduled show, starting **2 minutes before** each one
  so the heads are already protected when the lights come on.
- **Only selected playlists:** tick the playlists that use your moving heads in the list beside it. Air Defender checks
  only while one of those plays, scheduled or started by hand, and from 2 minutes before a scheduled one. The rest (a
  static display that runs late into the night, say) leave it on Standby. With nothing ticked it checks while any
  playlist plays. A sequence started on its own isn't part of a playlist, so it isn't checked: use **Check now**. The
  list is greyed out with **All scheduled shows**, and your ticks are kept.

**Using Remote Falcon?** A viewer's request plays from Remote Falcon's own playlist, inserted into your show. With
**Only selected playlists**, tick Remote Falcon's playlist too. (During a selected scheduled show Air Defender keeps
checking through requests anyway, because they play inside that show's schedule slot.)

To test a sequence at another time, use **Check now** on the dashboard (30 minutes to 4 hours), or the
**Air Defender: Check Now** command. It turns itself off when the time runs out.

## Master and Remote

| Your setup | Air Defender role |
|---|---|
| One FPP running the show | **Master** |
| An FPP sending E1.31/DDP to a Falcon or other controller with the heads | **Master** on that FPP (it covers the controller) |
| An FPP in **bridge** mode receiving data from the master | Nothing needed: the master covers it |
| An FPP in **remote** (multisync) mode with heads on its outputs | **Master** on the show FPP, **Remote** on each remote |

A remote plays its own copy of the sequence, so the master's overlays can't reach its heads. A **Remote** therefore
follows its master: it closes its own heads whenever the master is in safety mode, including the master's tests. If
it cannot reach the master for **5 seconds** (master off, unreachable, plugin missing, its loop not running, or it is
set to Remote), it closes its heads until the master is back. Install Air Defender on every remote that has heads;
a remote with no heads enabled does nothing.

A Remote finds its master automatically from FPP's MultiSync list; enter the **Master FPP address** if there is more
than one player on the network. If the master has an FPP **UI password**, enter it as **Master's UI password** on the
Remote (it logs in as `admin`, over your local network). The password is kept in its own file in FPP's `plugindata`
folder that only the fpp user can read (not with the other settings, which FPP's backups and crash reports copy), and
is never shown on the page.

## The dashboard

The **status banner** at the top says where things stand:

| Banner | Meaning |
|---|---|
| **All clear** (green) | No aircraft inside your limits; the show controls the shutters. |
| **Safety active** (orange) | The shutters are held closed, with the reason and when it started. |
| **Test running** (blue) | A test is closing the heads, with the time left. |
| **Standby** (grey-blue) | No show on, so not checking; shows the next scheduled show. |
| **Can't check the sky** (yellow) | The flight-data service isn't answering. |
| **Disabled** (grey) | Air Defender is switched off in Settings. |
| **Not running** (red) | The detection loop isn't running. Restart FPPD or reboot. |

Choose **Lite** (refreshes every 5 seconds) or **Detailed** (every 2 seconds, adds a **Sky** radar of nearby aircraft
and every head's state). Your choice is remembered in that browser. The dashboard only asks FPP for anything while it
is open.

- **Test** closes the heads for 5 seconds to 30 minutes so you can go outside and watch; a test on the master also
  closes every Remote's heads.
- **Last Event** shows only safety turning on or off and tests. Everything else is in the full log.
- **Clear log** deletes the Air Defender log.

## FPP commands

Available in playlists, schedules and command presets (after FPPD restarts once following install):

- **Air Defender: Start Test** (seconds): like Test on the dashboard.
- **Air Defender: Stop Test**: ends a running test.
- **Air Defender: Check Now** (minutes, 0 ends it): checks for aircraft outside show times, like Check now.

## Flight data

Air Defender asks a free community ADS-B service for the aircraft near your show:

| Service | Limits | Notes |
|---|---|---|
| **adsb.fi** (default) | Published: 1 request a second | Personal, non-commercial use. Data from [adsb.fi](https://adsb.fi). |
| **adsb.lol** | None published; some users report about 500 a day | Data from [adsb.lol](https://adsb.lol) under the ODbL. |
| **Local receiver** (tar1090 / dump1090-fa) | None | Reads a receiver on the FPP itself; nothing leaves the device. |

Turn on **Use the other as a backup** (it is off until you do, since it means the other service is also sent your
location) and, if adsb.fi or adsb.lol refuses, limits or doesn't answer, Air Defender asks the other in the same
check, so there's no gap. It tries the chosen one again every minute and goes back to it when it answers. A local
receiver never falls back to an online service.

The number of requests is your check interval over the time it checks: at 10 seconds, about 1,800 for a 5-hour show
night. The settings page shows the estimate as you change it.

airplanes.live is no longer offered: its API now refuses requests without an account. A saved airplanes.live setting
is switched to the default automatically.

## Privacy

- Your **show's location** and search radius go to the flight-data service you pick (adsb.fi, adsb.lol, or neither
  with a local receiver), only while Air Defender is checking.
- Pressing **Look it up** beside Show elevation sends the location once to api.open-meteo.com.
- A Remote asks its master for its status over your local network, with the master's UI password if you entered one.
- The log (`plugin-AirDefender.log` in FPP's logs folder) records the callsigns, distance and height of low aircraft
  that passed near your show. FPP rotates it daily and keeps **7 days**; **Clear log** deletes it now.
- Nothing is sent anywhere until you switch Air Defender on. The backup service and the elevation lookup are off
  until you use them.
- Uninstalling removes the log and the saved master password.
- Air Defender runs a background process while FPP is running, and holds the shutter channels you list while
  aircraft are near or during a test.

The full disclosure is in `pluginInfo.json` and is shown by Plugin Manager before you install or update.

## Troubleshooting

Start with the dashboard banner and **Last Event**; the full log (**Content Setup → File Manager → Logs →
plugin-AirDefender.log**) explains every change.

| You see | What to do |
|---|---|
| **Not running** | Restart FPPD (or reboot). If it keeps happening, the log shows why the loop stopped. |
| **Can't check the sky** / "No answer from the ADS-B feed" | Check the FPP has internet access. Try **Test connection**, or the other service. |
| "refused the request (HTTP 403)" or "(HTTP 429)" | The service is refusing or limiting requests. Turn on **backup**, raise **Check every**, or switch service. |
| "No show location" | Set the location in FPP's settings, or turn off **Use FPP's location** and enter one. |
| Aircraft right overhead aren't counted, or the radar looks wrong | The show location is probably off. Press **Check it on a map**; see [Check your location](#check-your-location). |
| A head's row is red: "Model not found" | The model was renamed or deleted in xLights. Pick it again; until then the last channel is still protected. |
| Heads don't close during a test | Check each head is **On**, the **FPP channel** shown matches the shutter channel, and the **closed** value is right for the fixture. |
| **Standby** when you expected checking | No scheduled show is on, or the playlist playing isn't ticked. Use **Check now**, or change **When to check**. |
| A Remote says "Lost the master" | Check the master is on, has Air Defender set to Master, and the address is right. If it says the master has a UI password, enter it on the Remote. |
| The dashboard says "FPP refused the shutter channels" | fppd isn't accepting overlays; restart FPPD. |

**Command-line check** (over SSH): asks the flight-data service once with your saved settings and lists every
aircraft, marking those inside your limits.

```bash
php /home/fpp/media/plugins/AirDefender/scripts/airdefender_check.php
```

Options `--url`, `--lat`, `--lon`, `--distance`, `--altitude` and `--elevation` override the saved settings. Exit code: 0 clear,
1 an aircraft is inside the limits, 2 could not check.

## How it works (for developers)

| Piece | File |
|---|---|
| Detection loop: reads settings every second, checks the feed, decides, holds or releases the overlays | `scripts/airdefender_loop.php` |
| Watchdog that keeps the loop running; started and stopped with fppd | `scripts/airdefender_supervise.sh`, `scripts/postStart.sh`, `scripts/preStop.sh` |
| Shutter overlays (`PUT /api/overlays/range/<ch>`, FPP's proxy to fppd) | `scripts/airdefender_controller.php` |
| Heads by model or manual channel | `scripts/airdefender_models.php` |
| Flight-data services, backup, fetching and parsing | `scripts/airdefender_feeds.php`, `scripts/airdefender_adsb.php` |
| Tests, Check now, Last Event, log | `scripts/airdefender_test.php`, `scripts/airdefender_status.php` |
| FPP's folders (media, logs, config), found the way FPP provides them | `scripts/airdefender_paths.php` |
| The master's UI password, in `plugindata/AirDefender/` (0600) | `scripts/airdefender_secret.php` |
| Dashboard and its endpoint | `www/index.php`, `www/test_api.php` |
| Settings page | `www/settings.php` |
| Status a Remote reads from its master | `www/master_status.php` |
| Install, update and uninstall hooks | `scripts/fpp_install.sh`, `scripts/fpp_upgrade.sh`, `scripts/fpp_uninstall.sh` |

Runtime files written on the device (not in git): `state.json`, `test.json`, `override.json`, `last_event.json` and
`loop.lock` in the plugin folder, `plugin-AirDefender.log` in FPP's logs folder, and the master password in
`plugindata/AirDefender/`. The pages follow FPP's light and dark themes through CSS variables.

## License

GNU General Public License v2. See [LICENSE](LICENSE).
