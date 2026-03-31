# GOOAT — Terminal Commands

All Craft console commands are run from inside the DDEV web container:
```
php craft <command>
```
Or if you're already inside the container (`ddev ssh`):
```
php craft <command>
```

---

## Import Commands

### Import Drivers
Fetches F1 drivers from the Jolpica API and creates driver entries for a season.
```bash
php craft f1-gooat/import/drivers --site=season2026
```
Options:
- `--site` *(required)* — site handle, e.g. `season2026`
- `--year` *(optional)* — season year, defaults to year derived from the site handle

---

### Import Races
Fetches the F1 race schedule from the Jolpica API and creates race entries.
```bash
php craft f1-gooat/import/races --site=season2026
```
Options:
- `--site` *(required)* — site handle, e.g. `season2026`
- `--year` *(optional)* — season year, defaults to year derived from the site handle

---

### Update Driver Teams
Updates driver team assignments using the current F1 constructors standings.
```bash
php craft f1-gooat/import/update-teams --site=season2026
```
Options:
- `--site` *(required)* — site handle, e.g. `season2026`

---

### Clone Players to New Season
Copies all players from one season to another. Use this when setting up a new season.
```bash
php craft f1-gooat/import/clone-players --site=season2026 --from=season2025
```
Options:
- `--site` *(required)* — target site handle (new season)
- `--from` *(required)* — source site handle (previous season)

---

### Import Predictions from JSON
Bulk imports predictions from a JSON file.
```bash
php craft f1-gooat/import/predictions --site=season2025 --file=predictions.json
```
Options:
- `--site` *(required)* — site handle
- `--file` *(required)* — path to the JSON file

Expected JSON format:
```json
[
  { "round": 1, "playerId": 123, "driverCode": "VER", "selectionOrder": 1, "boosterUsed": false },
  { "round": 1, "playerId": 456, "driverCode": "SKIP", "selectionOrder": 2, "boosterUsed": false }
]
```

---

## Cron / Scheduled Tasks

### Fetch Race Results
Scans all sites for races ready for result fetching and queues the result processing jobs.
Intended to run every hour on Sundays via a cron job.
```bash
php craft f1-gooat/cron/fetch-results
```

---

## New Season Setup — Recommended Order

1. Create the new site in Craft CMS admin
2. Import drivers:
   ```bash
   php craft f1-gooat/import/drivers --site=season2026
   ```
3. Import race schedule:
   ```bash
   php craft f1-gooat/import/races --site=season2026
   ```
4. Update driver teams:
   ```bash
   php craft f1-gooat/import/update-teams --site=season2026
   ```
5. Clone players from previous season:
   ```bash
   php craft f1-gooat/import/clone-players --site=season2026 --from=season2025
   ```

---

## Frontend

```bash
# Start development server
npm run dev

# Build production assets
npm run build
```
