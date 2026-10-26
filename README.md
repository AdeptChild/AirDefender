
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

### **Developer Plugin Loader (recommended)**

1. SSH into your FPP device  
2. Navigate to the plugin directory:

   ```bash
   cd /home/fpp/media/plugins/

## Node Role

The settings page has a **Node Role** selector at the top:

- **Standalone** – local / DDP output (default).
- **Master** – detects aircraft and sends commands to remote FPPs.
- **Remote** – receives commands from a master; Location, Safety Thresholds and ADS-B Feed are greyed out.

**Step 1 is UI-only:** the role is saved to the config but does not yet change any runtime behavior. Greyed-out values are not submitted, so previously saved values are preserved.

## Moving Heads (Step 2 UI)

The settings page has a compact **Moving Heads** section with up to 8 heads (MH1–MH8), each with an enabled toggle, shutter channel and closed value. The open value is automatically the opposite of the closed value. **Node Role** appears near the top, after Plugin Status. The old Controller group is hidden from the UI, but its legacy keys (`controllerType`, `blackoutStartChannel`, `blackoutEndChannel`) are still kept in the config.

## How the blackout works

When safety mode turns on, each enabled head's shutter channel is held at its **closed value** with an FPP channel overlay (`/api/overlays/range`). fppd writes that value on every output frame, over the running sequence, so it reaches the fixture however the channel is output: a Kulp's serial DMX port, or E1.31/DDP to a Falcon. When safety mode turns off, the overlay is removed and the sequence controls the shutter again (it is not forced open).

The DMX signal is never cut on purpose: most moving heads and lasers hold their last values when DMX stops, which could leave a beam frozen open.

Safety mode turns on when:

- a **Test** is running from the dashboard,
- an aircraft is inside the distance **and** below the altitude threshold (aircraft on the ground are ignored),
- one was, within the last `clearDelaySec` seconds (default 30), so a plane on the edge of the radius doesn't flash the lights,
- or the ADS-B feed stops answering while safety mode is on: with no data it stays safe until the feed comes back.

The detection loop starts with fppd (`scripts/postStart.sh`) and stops with it (`scripts/preStop.sh`). It re-reads the settings every second, so changes apply without restarting fppd. Everything it does is logged to `/home/fpp/media/logs/airdefender.log`.

**Node Role:** Standalone and Master both detect aircraft and close their own heads. Remote does not detect yet (the master-to-remote link is a later step), but a dashboard Test still works on it.
