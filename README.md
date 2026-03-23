# Flapping Detector for Zabbix

A Zabbix 7.0+ frontend module that detects **flapping triggers** — those changing state too frequently between PROBLEM and OK — and surfaces them with a badge on the Problems list plus a dedicated investigation view.

Developed by [Virgilio Borges](https://github.com/virgiliojr94).

---

## What is flapping?

As defined by the Cloudflare Observability team: an alert that **changes state too frequently** (firing → resolved → firing...). This indicates the trigger threshold is too sensitive, or the `for duration` period is too short. Flapping alerts cause on-call fatigue without conveying real incidents.

---

## Features

- **Badge on Problems list** — a colored pill (⚡ N) appears inline on each flapping trigger row and links directly to the trigger history view.
- **Global flapping view** — `Monitoring → Flapping Detector` shows all currently flapping triggers sorted by flip count, with severity classification and filters.
- **Flip history** — per-trigger detail page with hourly bar chart of flip activity, full state timeline (PROBLEM / OK), and an actionable recommendation.
- **Configurable thresholds** — time window (1h / 6h / 12h / 24h / 7d), minimum flips, host group filter, plus default thresholds loaded from `config/flapping_config.json`.

---

## Severity classification

| Severity | Condition |
|---|---|
| 🔴 High   | ≥ 10 flips **or** rate ≥ 1 flip/h |
| 🟠 Medium | ≥ 5 flips  **or** rate ≥ 0.5 flip/h |
| 🟡 Low    | ≥ 3 flips (configurable minimum) |

---

## Algorithm

```
time_from = now - time_window_hours * 3600

events = event.get(source=TRIGGER, time_from=time_from, sorted by triggerid+clock)

for each trigger:
    transitions = count(events[i].value != events[i-1].value)
    flap_rate   = transitions / time_window_hours
    if transitions >= min_flaps → mark as flapping
```

A "flip" is any PROBLEM→OK or OK→PROBLEM state change. This is the same metric Cloudflare uses in their alert swimlane timeline to identify noisy alerts.

---

## How to fix flapping

1. **Increase `for duration`** on the trigger rule — adds time tolerance before an alert fires. The condition must be met continuously for N minutes before the alert triggers.
2. **Widen the threshold** — if the value is oscillating around the boundary, move the boundary.
3. **Use time-based functions** — replace `last()` with `avg(5m)` or `min(3m)` to smooth out spikes.

---

## Requirements

- Zabbix 7.0+
- PHP 8.0+

---

## Installation

```bash
cp -r FlappingDetector /usr/share/zabbix/modules/
```

1. Go to **Administration → General → Modules**
2. Find **Flapping Detector** and click **Enable**
3. The module adds **Monitoring → Flapping Detector** to the menu
4. Badges are automatically injected into **Monitoring → Problems**

### Updating an existing installation

If the module is already installed:

1. Overwrite the module files in `/usr/share/zabbix/modules/FlappingDetector`
2. Go to **Administration → General → Modules**
3. Disable and enable **Flapping Detector** again to force Zabbix to reload the manifest and assets
4. Hard refresh the browser to invalidate old JavaScript and CSS

---

## Configuration

The module can load default values from:

```text
config/flapping_config.json
```

If the file is missing, the module falls back to built-in defaults in `CControllerFlappingBase.php`.

Currently used settings:

- `default_time_window`
- `default_min_flaps`
- `severity_thresholds.high`
- `severity_thresholds.medium`
- `severity_thresholds.low`

The filters on the Flapping Detector page still override these defaults per request.

---

## File layout

```
FlappingDetector/
├── manifest.json                            # Registers actions and global assets
├── Module.php
├── actions/
│   ├── CControllerFlappingBase.php          # Shared defaults / severity / JSON helpers
│   ├── CControllerFlappingView.php          # Global flapping list
│   ├── CControllerFlappingHistory.php       # Per-trigger history
│   └── CControllerFlappingBadge.php         # Lightweight badge data endpoint
├── views/
│   ├── flapping.view.php                    # Global list view
│   ├── flapping.history.view.php            # History view
│   └── js/
│       └── flapping.js.php                  # PHP → JS config bridge
├── assets/
│   ├── css/
│   │   └── flapping.css
│   └── js/
│       ├── flapping-badge.js                # Badge injector for Problems list
│       └── flapping.js                      # Canvas chart renderer
└── config/
    └── flapping_config.json                 # Default thresholds
```

---

## License

MIT — see LICENSE.

## Author

Virgilio Borges — [https://github.com/virgiliojr94](https://github.com/virgiliojr94)
