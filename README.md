
# Air Defender  
### ADS‑B Aircraft Safety Plugin for Falcon Player (FPP)

Air Defender monitors nearby ADS‑B aircraft and automatically disables selected DMX outputs when aircraft enter a defined safety radius. This helps prevent bright lighting effects from pointing skyward while aircraft are overhead.

This plugin is designed for FPP 10+ and supports Raspberry Pi, BeagleBone, and other compatible platforms.

---

## ✨ Features

- Real‑time ADS‑B aircraft detection  
- Adjustable distance threshold (nautical miles)  
- Automatic DMX output shutdown when aircraft are nearby  
- Automatic DMX restore when clear  
- Optional location override  
- Adjustable ADS‑B fetch interval  
- Simple configuration through FPP Plugin Settings  
- Lightweight and efficient loop  
- Local logging (`airdefender.log`)

---

## 📦 Installation

### From FPP's Plugin Manager (recommended)

1. In FPP, open **Content Setup → Plugin Manager**.
2. Paste this into the plugin info URL box and press **Retrieve Plugin Info**:

   ```
   https://raw.githubusercontent.com/AdeptChild/AirDefender/master/pluginInfo.json
   ```

3. Press **Install**, then restart fppd when FPP asks.

### By hand (SSH)

```bash
cd /home/fpp/media/plugins/
git clone https://github.com/AdeptChild/AirDefender.git
```

Then restart fppd. To update later, press **Update** for Air Defender in Plugin Manager; the detection loop restarts with the new code by itself.

## FPP Commands

Air Defender adds two commands you can use in playlists, schedules and command presets (they appear after fppd restarts once following install):

- **Air Defender: Start Test** – closes the shutters for the given number of seconds (5–1800), exactly like Start Test on the dashboard. On the master it closes every Remote's heads too.
- **Air Defender: Stop Test** – ends a running test early.

## Node Role

The settings page has a **Node Role** selector near the top:

- **Master** (default) – detects aircraft and closes its own heads. Use this on a single FPP, and on the FPP that runs the show. Heads fed by the master over DDP/E1.31 (a Falcon, or an FPP in bridge mode) are covered by the master itself.
- **Remote** – for an FPP in remote (multisync) mode, which plays its own copy of the sequence, so the master's overlay can't reach its heads. It follows the master instead: it closes its own heads whenever the master is in safety mode, including during a master test. Location, Safety Thresholds and ADS-B Feed are greyed out.

Install the plugin on the master and on **every** remote with heads (set one to Master, the rest to Remote); a remote with no heads enabled just does nothing.

A remote finds its master automatically from FPP's MultiSync list, or uses the **Master FPP Address** setting if filled in (needed when more than one player is on the network). It checks the master every second. If it gets no good answer for 5 seconds (master off, unreachable, plugin missing, its detection loop not running, or it is set to Remote) the remote **closes its heads** until the master is back.

Older settings saved as "Standalone" run as Master.

## Moving Heads

The settings page has up to 8 heads (MH1–MH8). For each, turn it on and give it an optional **name** (used in the log), then either:

- pick its **model** from FPP's model list, or
- choose **Manual channel** and enter the head's **Start Ch.** from xLights (e.g. 82448).

Then enter the **Shutter Ch.**: the shutter's channel *within the fixture*, from its DMX chart (e.g. 7). Air Defender does the math (82448 + 7 − 1 = FPP channel 82454) and shows the result on the row. Set the **closed value** (0–255) from the DMX chart.

To get heads into the model list, upload from xLights with **FPP Connect** with the **Models** column set to **All**. **Local** only includes models on that FPP's own outputs, so it leaves out heads on a Falcon or other controller. You can also add models by hand on FPP's Pixel Overlay Models page. FPP does not create models for DMX fixtures by itself.

Heads picked by model are looked up by name, so re-patching in xLights and uploading again is followed automatically. If a model is renamed or deleted, the row is flagged and Air Defender keeps protecting the channel it last resolved to, and logs a warning. Settings from before this version (an absolute channel) carry over as Manual with that channel as the start.

## How the blackout works

When safety mode turns on, each enabled head's shutter channel is held at its **closed value** with an FPP channel overlay (`/api/overlays/range`). fppd writes that value on every output frame, over the running sequence, so it reaches the fixture however the channel is output: a Kulp's serial DMX port, or E1.31/DDP to a Falcon. When safety mode turns off, the overlay is removed and the sequence controls the shutter again (it is not forced open).

The DMX signal is never cut on purpose: most moving heads and lasers hold their last values when DMX stops, which could leave a beam frozen open.

Safety mode turns on when:

- a **Test** is running from the dashboard,
- an aircraft is inside the distance **and** below the altitude threshold (aircraft on the ground are ignored),
- one was, within the **Hold After Aircraft Leaves** time (default 30 seconds), so a plane on the edge of the radius doesn't flash the lights,
- or the ADS-B feed stops answering while an aircraft was near: with no data it stays safe until the feed comes back. With **Close Heads If Feed Is Down** on, any feed outage closes the heads.

The detection loop starts with fppd (`scripts/postStart.sh`) and stops with it (`scripts/preStop.sh`). It re-reads the settings every second, so changes apply without restarting fppd. Everything it does is logged to `/home/fpp/media/logs/airdefender.log`, which keeps the last 7 days (it lists aircraft that passed nearby) and can be cleared from the dashboard. The dashboard's **Right Now** card shows the live state (flight data or master connection, and each head's shutter), and **Last Event** shows only safety turning on or off and tests.

**Node Role:** see above. A Remote follows its master; a Test started on a Remote closes only that remote's heads.
