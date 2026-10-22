
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
