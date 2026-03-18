# F1 GOOAT - Formula 1 Prediction Game

A multiplayer F1 fantasy game where players predict who will finish **10th (P10)** in each race. Built on **Craft CMS 5** with a custom PHP module, Twig templates, and a JavaScript frontend.

---

## Table of Contents

1. [How the Game Works](#how-the-game-works)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Getting Started (Local Dev)](#getting-started-local-dev)
5. [PHP Module — Backend Logic](#php-module--backend-logic)
   - [Module.php — The Brain](#modulephp--the-brain)
   - [Services (Business Logic)](#services-business-logic)
   - [Controllers (Web Endpoints)](#controllers-web-endpoints)
   - [Console Commands (CLI)](#console-commands-cli)
   - [Queue Jobs](#queue-jobs)
6. [Templates — Frontend Pages](#templates--frontend-pages)
7. [JavaScript — Interactive Features](#javascript--interactive-features)
8. [CSS & Styling](#css--styling)
9. [Database & Content Structure](#database--content-structure)
10. [API Endpoints Reference](#api-endpoints-reference)
11. [Caching](#caching)
12. [Multi-Site / Multi-Season](#multi-site--multi-season)
13. [Admin Features](#admin-features)
14. [Environment Variables](#environment-variables)
15. [Common Tasks & How-Tos](#common-tasks--how-tos)

---

## How the Game Works

### The Concept

Each race weekend, every player picks one F1 driver they think will finish **exactly 10th**. Points are awarded based on how close the picked driver finishes to P10.

### Points System

| Driver Finishes At | Distance from P10 | Points Earned |
|--------------------|--------------------|---------------|
| P10 (exact!)       | 0                  | **25 pts**    |
| P9 or P11          | 1                  | 18 pts        |
| P8 or P12          | 2                  | 15 pts        |
| P7 or P13          | 3                  | 12 pts        |
| P6 or P14          | 4                  | 10 pts        |
| P5 or P15          | 5                  | 8 pts         |
| P4 or P16          | 6                  | 6 pts         |
| P3 or P17          | 7                  | 4 pts         |
| P2 or P18          | 8                  | 2 pts         |
| P1 or P19          | 9                  | 1 pt          |
| P20 or DNF/DSQ     | 10+                | 0 pts         |

### Race Lifecycle

Every race goes through four statuses in order:

```
upcoming → selection_open → selection_closed → completed
```

1. **Upcoming** — Race is on the schedule but voting hasn't started yet
2. **Selection Open** — Players take turns picking their driver (draft-style)
3. **Selection Closed** — All players have picked, waiting for the real race to happen
4. **Completed** — Real race results are in, points have been calculated

### Draft Order (Who Picks When)

Players don't all pick at the same time. Instead, they take turns in **reverse standings order** — the player in last place picks first, the leader picks last. This balances the game so trailing players get first pick of the best drivers.

### Booster

Each player gets **one booster per season**. When activated, it **doubles** the points earned for that race's prediction. Use it wisely!

### Skipping

If a player takes too long, any other player can skip their turn. The skipped player gets 0 points for that race.

---

## Tech Stack

| Layer      | Technology                                  |
|------------|---------------------------------------------|
| CMS        | Craft CMS 5.9.14 (PHP)                      |
| PHP        | 8.2 / 8.3                                   |
| Database   | MySQL 8.0                                   |
| Templates  | Twig (Craft's template engine)              |
| CSS        | Tailwind CSS 4                               |
| JavaScript | Vanilla ES6 modules                          |
| Charts     | Chart.js 4                                   |
| Animations | GSAP 3.13                                    |
| Build Tool | Vite 7                                       |
| Local Dev  | DDEV (Docker)                                |
| F1 Data    | Jolpica API (free F1 stats API)              |

---

## Project Structure

```
gooat/
│
├── modules/f1gooat/              # 🧠 ALL backend logic lives here
│   ├── Module.php                #    Bootstrap, routes, global helpers
│   ├── PointsCalculator.php      #    Scoring formula
│   ├── RaceStatus.php            #    Status constants (upcoming, selection_open, etc.)
│   ├── CacheService.php          #    Cache management with tag invalidation
│   ├── SelectionService.php      #    Draft order logic (who picks when)
│   ├── RaceResultsService.php    #    Results processing pipeline
│   ├── controllers/              #    Web request handlers (6 files)
│   │   ├── AuthController.php
│   │   ├── FrontendController.php
│   │   ├── PredictionController.php
│   │   ├── RaceController.php
│   │   ├── LeaderboardController.php
│   │   └── UpdateController.php
│   ├── console/controllers/      #    CLI commands
│   │   ├── ImportController.php
│   │   └── CronController.php
│   └── jobs/                     #    Background queue jobs
│       └── FetchRaceResultsJob.php
│
├── templates/                    # 🎨 ALL page templates (Twig)
│   ├── index.twig                #    Homepage (standings + next race)
│   ├── f1/                       #    Game pages
│   │   ├── _layout.twig          #    Shared layout (header/footer)
│   │   ├── select-driver.twig    #    Driver voting page
│   │   ├── standings.twig        #    Season leaderboard
│   │   ├── race-results.twig     #    Race results breakdown
│   │   ├── race-list.twig        #    All races schedule
│   │   ├── driver-list.twig      #    Driver roster
│   │   ├── driver-profile.twig   #    Individual driver stats
│   │   ├── player-profile.twig   #    Individual player stats
│   │   ├── login.twig            #    Email login form
│   │   └── _partials/            #    Reusable template pieces
│   │       ├── header.twig
│   │       ├── footer.twig
│   │       ├── playerStanding.twig
│   │       ├── raceHistoryRow.twig
│   │       └── raceCards/        #    Race card per status
│   │           ├── _card.twig
│   │           ├── upcoming.twig
│   │           ├── selection_open.twig
│   │           ├── selection_closed.twig
│   │           └── completed.twig
│   └── _layouts/                 #    Base HTML layouts
│       ├── base.twig
│       └── site.twig
│
├── src/                          # 💻 Frontend source files
│   ├── js/
│   │   ├── app.js                #    Main entry (lazy-loads features)
│   │   └── parts/                #    Feature modules
│   │       ├── driverSelection.js    # Card grid, voting, confirm modal
│   │       ├── seasonChart.js        # Chart.js leaderboard graph
│   │       ├── skipPlayer.js         # Skip turn button
│   │       ├── refetchResults.js     # Manual results fetch button
│   │       ├── adminActions.js       # Admin sync buttons
│   │       ├── playerRaceByRace.js   # Expandable race history
│   │       ├── a11y-dialog.js        # Accessible modals (login)
│   │       ├── pageHeader.js         # Sticky header + site switcher
│   │       ├── formie.js             # Craft Formie forms
│   │       ├── design-grid.js        # Dev grid overlay
│   │       └── debounce.js           # Utility function
│   └── css/
│       ├── app.css               #    Main CSS entry point
│       └── 3_components/         #    Component-specific styles
│           ├── f1/               #    F1 game components
│           │   ├── f1-F1DriverCard.css
│           │   ├── f1-cards.css
│           │   ├── f1-badges.css
│           │   ├── f1-login.css
│           │   └── f1-table.css
│           └── globals/          #    Global components
│               ├── header.css
│               ├── footer.css
│               ├── buttons.css
│               └── headroom.css
│
├── config/                       # ⚙️ Craft & app configuration
│   ├── app.php                   #    Loads the f1gooat module
│   ├── general.php               #    General Craft settings
│   ├── vite.php                  #    Vite asset integration
│   ├── routes.php                #    (empty — routes defined in Module.php)
│   └── project/                  #    Content structure (auto-managed by Craft)
│       ├── project.yaml
│       ├── sections/             #    Section definitions
│       ├── entryTypes/           #    Entry type definitions
│       └── fields/               #    Field definitions
│
├── web/                          # 🌐 Public web root
│   └── dist/                     #    Built assets (Vite output, git-tracked)
│
├── .ddev/                        # 🐳 Docker/DDEV configuration
│   ├── config.yaml               #    PHP 8.3, MySQL 8.0, nginx
│   └── db_snapshots/             #    Database snapshots for resets
│
├── vite.config.js                # Build tool config
├── postcss.config.js             # PostCSS/Tailwind
├── package.json                  # Node dependencies
├── composer.json                 # PHP dependencies
└── .env                          # Environment variables (not in git)
```

---

## Getting Started (Local Dev)

### Prerequisites
- [DDEV](https://ddev.readthedocs.io/) installed
- Node.js 18+ and npm

### Setup

```bash
# 1. Start DDEV (starts PHP, MySQL, Nginx containers)
ddev start

# 2. Install PHP dependencies
ddev composer install

# 3. Install Node dependencies
ddev npm install

# 4. Import a database snapshot (if available)
ddev snapshot restore

# 5. Build frontend assets
ddev npm run build

# 6. (Optional) Start Vite dev server for hot-reload
ddev npm run dev
```

### Accessing the Site
- **Frontend:** The URL shown by `ddev describe` (e.g. `https://gooat.ddev.site`)
- **Craft Control Panel:** Add `/admin` to the URL
- **Vite Dev Server:** Port 5173 (auto-configured)

---

## PHP Module — Backend Logic

Everything lives in `modules/f1gooat/`. This is a **Craft CMS module** — it's registered in `config/app.php` and bootstraps automatically.

### Module.php — The Brain

**File:** `modules/f1gooat/Module.php`

This is the entry point. When Craft starts, `Module::init()` runs and does two things:

#### 1. Registers All URL Routes

Every URL in the game maps to a controller action:

```
/select/123           → FrontendController::actionSelectDriver(123)
/standings            → FrontendController::actionStandings()
/results/123          → FrontendController::actionRaceResults(123)
/races                → FrontendController::actionRaceList()
/drivers              → FrontendController::actionDriverList()
/driver/456           → FrontendController::actionDriverProfile(456)
/player/789           → FrontendController::actionPlayerProfile(789)
/prediction/submit    → PredictionController::actionSubmitPrediction()
/prediction/skip      → PredictionController::actionSkipPlayer()
/player-login         → AuthController::actionLogin()
/player-logout        → AuthController::actionLogout()
/update/sync-drivers  → UpdateController::actionSyncDrivers()
/update/sync-races    → UpdateController::actionSyncRaces()
/race/fetch-results/X → RaceController::actionFetchRaceResults(X)
... etc
```

#### 2. Injects Global Template Variables

Every Twig template automatically has access to:

| Variable             | What It Is                                      |
|----------------------|-------------------------------------------------|
| `currentPlayer`      | The logged-in player entry (or `null`)           |
| `availableSites`     | All season sites (for the season switcher)       |
| `currentSeasonYear`  | Year number (e.g. `2026`)                        |
| `currentSite`        | The active Craft site object                     |
| `pointsMap`          | The points table (used to show scoring rules)    |

#### Key Helper Methods

| Method | What It Does |
|--------|-------------|
| `getCurrentPlayer()` | Looks up the player entry by the email stored in the session |
| `calculateSeasonStandings($siteId)` | Computes the full leaderboard (with caching) |
| `getCurrentSeasonYear()` | Extracts the year from the site handle (e.g. `season2026` → `2026`) |
| `getApiBaseUrl()` | Returns the Jolpica API base URL from env |
| `getAvailableSites()` | Returns all sites in the site group (for season switching) |

---

### Services (Business Logic)

These files contain the core game logic. Controllers call these services — they don't contain game logic themselves.

#### PointsCalculator.php

**File:** `modules/f1gooat/PointsCalculator.php`

Dead simple: takes a driver's actual finishing position, returns points earned.

```php
PointsCalculator::calculate(10);  // 25 (perfect P10)
PointsCalculator::calculate(11);  // 18 (1 off)
PointsCalculator::calculate(1);   // 1  (9 off)
PointsCalculator::calculate(20);  // 0  (10+ off)
```

The `POINTS_MAP` constant is also exposed to Twig templates as `pointsMap` for displaying the scoring table.

#### RaceStatus.php

**File:** `modules/f1gooat/RaceStatus.php`

Just four constants so we don't use magic strings everywhere:

```php
RaceStatus::UPCOMING          // 'upcoming'
RaceStatus::SELECTION_OPEN    // 'selection_open'
RaceStatus::SELECTION_CLOSED  // 'selection_closed'
RaceStatus::COMPLETED         // 'completed'
```

#### SelectionService.php

**File:** `modules/f1gooat/SelectionService.php`

Handles the draft order — determines whose turn it is to pick.

| Method | What It Does |
|--------|-------------|
| `getCurrentSelector($raceId, $siteId)` | Returns the player entry whose turn it is. Uses reversed standings (last place picks first). Appends players not yet in standings. Returns `null` when all players have picked. |
| `getSelectedCount($raceId)` | How many predictions exist for this race |
| `hasUsedBooster($player, $siteId)` | Has this player already used their booster this season? |

**How draft order works internally:**
1. Get the season standings (leaderboard)
2. Reverse the order (so last place is first)
3. Append any players who aren't in standings yet (no predictions in any race)
4. The player at index `[number of predictions already made]` is the current selector

#### RaceResultsService.php

**File:** `modules/f1gooat/RaceResultsService.php`

Handles everything after a real F1 race finishes. The main method is `processRaceResults()` which runs the full pipeline:

```
Fetch API results → Format → Save to race entry → Calculate points for each prediction
→ Apply booster multiplier → Update player standings → Open next race → Clear caches
```

| Method | What It Does |
|--------|-------------|
| `formatResults($apiResults)` | Converts Jolpica API response into our internal format |
| `processRaceResults($race, $results)` | Runs the full pipeline (see above) |
| `calculatePointsForRace($race, $siteId)` | Calculates and saves points for all predictions of a race |
| `updatePlayerStandings($siteId)` | Recalculates totalPoints and currentStanding for all players |
| `openNextRace($completedRace)` | Finds the next `upcoming` race and sets it to `selection_open` |
| `findDriverResult($results, $driverCode)` | Finds a specific driver in the results array |

#### CacheService.php

**File:** `modules/f1gooat/CacheService.php`

Manages server-side caching with **tag-based invalidation**. When data changes, we invalidate specific cache tags instead of clearing everything.

**Cache Tags:**
| Tag | Used For |
|-----|----------|
| `f1.standings` | Leaderboard data |
| `f1.seasonChart` | Season progress chart |
| `f1.races` | Race schedule |
| `f1.drivers` | Driver roster |
| `f1.predictions` | All predictions |
| `f1.players` | Player entries |

**Cache Durations:**
| Duration | Length | Used For |
|----------|--------|----------|
| `DURATION_SHORT` | 5 minutes | Active selection pages |
| `DURATION_MEDIUM` | 1 hour | Standings, charts |
| `DURATION_LONG` | 24 hours | Completed race data, driver profiles |

**Invalidation Methods:**
| Method | Clears |
|--------|--------|
| `invalidateAfterPrediction()` | predictions, standings, seasonChart |
| `invalidateAfterRaceResults()` | Everything (all tags) |
| `invalidateAfterRaceSync()` | races |
| `invalidateAfterDriverSync()` | drivers |

---

### Controllers (Web Endpoints)

Controllers handle HTTP requests. They validate input, call services, and return responses (JSON or rendered templates).

#### AuthController.php

**File:** `modules/f1gooat/controllers/AuthController.php`

Simple email-based login. No passwords — players are trusted.

| Action | URL | Method | What It Does |
|--------|-----|--------|-------------|
| `actionLogin()` | `/player-login` | GET/POST | Shows login form (GET) or validates email and stores in session (POST) |
| `actionLogout()` | `/player-logout` | GET | Clears session, redirects to homepage |

**How login works:**
1. Player enters their email
2. System looks for a player entry with that `playerEmail` on the current site
3. If found, stores the email in the PHP session
4. `Module::getCurrentPlayer()` reads this session value on every request

#### FrontendController.php

**File:** `modules/f1gooat/controllers/FrontendController.php`

Renders all the game pages. Each action prepares data and passes it to a Twig template.

| Action | URL | Template | What It Shows |
|--------|-----|----------|--------------|
| `actionSelectDriver($raceId)` | `/select/123` | `f1/select-driver` | Driver voting page with grid, selection status, booster toggle |
| `actionStandings()` | `/standings` | `f1/standings` | Season leaderboard with chart |
| `actionRaceResults($raceId)` | `/results/123` | `f1/race-results` | Race result breakdown, predictions vs actual |
| `actionRaceList()` | `/races` | `f1/race-list` | All races in the schedule |
| `actionDriverList()` | `/drivers` | `f1/driver-list` | Active driver roster |
| `actionDriverProfile($driverId)` | `/driver/456` | `f1/driver-profile` | Driver stats (wins, podiums, pick stats) |
| `actionPlayerProfile($playerId)` | `/player/789` | `f1/player-profile` | Player stats (race history, avg points) |

#### PredictionController.php

**File:** `modules/f1gooat/controllers/PredictionController.php`

Handles the actual voting mechanics. All responses are JSON.

| Action | URL | Method | What It Does |
|--------|-----|--------|-------------|
| `actionSubmitPrediction()` | `/prediction/submit` | POST | Submit a driver pick. Validates turn, driver availability, booster. Creates prediction entry. Auto-closes selection when all players have picked. |
| `actionGetAvailableDrivers()` | `/prediction/available-drivers` | GET | Returns drivers not yet picked for a race |
| `actionGetSelectionStatus()` | `/prediction/selection-status` | GET | Returns race status, whose turn it is, player count |
| `actionSkipPlayer()` | `/prediction/skip` | POST | Skips current player's turn (creates prediction with driverId=`SKIP`, 0 points) |

#### RaceController.php

**File:** `modules/f1gooat/controllers/RaceController.php`

Fetches real F1 results and calculates points.

| Action | URL | Method | What It Does |
|--------|-----|--------|-------------|
| `actionFetchRaceResults($raceId)` | `/race/fetch-results/123` | GET | Fetches results from Jolpica API, runs full processing pipeline |
| `actionCalculatePoints($raceId)` | `/race/calculate-points/123` | GET | Recalculates points from existing results (without re-fetching API) |

#### LeaderboardController.php

**File:** `modules/f1gooat/controllers/LeaderboardController.php`

Public JSON API for leaderboard data (used by Chart.js and can be used externally).

| Action | URL | What It Returns |
|--------|-----|-----------------|
| `actionGetStandings()` | `/leaderboard/standings` | Full season standings with position changes |
| `actionGetRaceBreakdown($raceId)` | `/leaderboard/race-breakdown/123` | All predictions for a race with actual P10 |
| `actionGetSeasonChart()` | `/leaderboard/season-chart` | Cumulative points per player per race (for Chart.js) |
| `actionGetPlayerStats($playerId)` | `/leaderboard/player-stats/789` | Individual player statistics |

#### UpdateController.php

**File:** `modules/f1gooat/controllers/UpdateController.php`

Admin sync actions — pulls data from the Jolpica F1 API.

| Action | URL | Method | What It Does |
|--------|-----|--------|-------------|
| `actionSyncDrivers()` | `/update/sync-drivers` | POST | Imports/updates driver roster from API |
| `actionSyncRaces()` | `/update/sync-races` | POST | Imports/updates race schedule from API |
| `actionFetchAllResults()` | `/update/fetch-results` | POST | Batch-fetches results for all `selection_closed` races |

---

### Console Commands (CLI)

Run these via `ddev craft <command>` in the terminal.

**File:** `modules/f1gooat/console/controllers/ImportController.php`

| Command | What It Does |
|---------|-------------|
| `ddev craft f1-gooat/import/drivers --site=season2026` | Import drivers from Jolpica API |
| `ddev craft f1-gooat/import/races --site=season2026 --year=2026` | Import race schedule |
| `ddev craft f1-gooat/import/update-teams --site=season2026` | Update driver team names from standings API |
| `ddev craft f1-gooat/import/clone-players --site=season2026 --from=season2025` | Copy player roster to a new season |
| `ddev craft f1-gooat/import/predictions --site=season2025 --file=predictions.json` | Import predictions from a JSON file |

**File:** `modules/f1gooat/console/controllers/CronController.php`

| Command | What It Does |
|---------|-------------|
| `ddev craft f1-gooat/cron/fetch-results` | Checks all sites for races ready for results, queues fetch jobs |

---

### Queue Jobs

**File:** `modules/f1gooat/jobs/FetchRaceResultsJob.php`

A background job that fetches race results from the API. Used by the cron controller to process results asynchronously. Takes a `raceId`, fetches from Jolpica API, and runs `RaceResultsService::processRaceResults()`.

---

## Templates — Frontend Pages

Templates use Twig (Craft's template engine). They live in `templates/` and render the HTML pages.

### Layout Hierarchy

```
_layouts/base.twig          ← HTML skeleton (<html>, <head>, <body>)
  └── _layouts/site.twig    ← Site-wide wrapper
      └── f1/_layout.twig   ← F1 game layout (header + footer + main block)
          └── f1/*.twig     ← Individual pages
```

Every page template extends `f1/_layout.twig` and fills in the `{% block main %}` block.

### Page Templates

| Template | URL | Description |
|----------|-----|-------------|
| `index.twig` | `/` | **Homepage.** Shows next race card (with voting progress), season standings preview, recent race results. |
| `f1/select-driver.twig` | `/select/123` | **Voting page.** The core of the game. Shows: whose turn it is, available driver grid, booster toggle, confirm modal, recently selected list, already-taken drivers. |
| `f1/standings.twig` | `/standings` | **Leaderboard.** Season standings table with position change indicators, season progress chart (Chart.js), stats summary. |
| `f1/race-results.twig` | `/results/123` | **Race results.** Shows the actual P10 driver, each player's prediction with points earned, full race classification grid. |
| `f1/race-list.twig` | `/races` | **Schedule.** All races listed with status-specific cards (color-coded by status). |
| `f1/driver-list.twig` | `/drivers` | **Driver roster.** All active drivers grouped by team. |
| `f1/driver-profile.twig` | `/driver/456` | **Driver stats.** F1 stats (wins, podiums, DNFs) + game stats (times picked, avg points generated). |
| `f1/player-profile.twig` | `/player/789` | **Player stats.** Race history, perfect predictions, average points, favorite drivers. |
| `f1/login.twig` | `/player-login` | **Login form.** Simple email input. |

### Partials (Reusable Template Pieces)

| Partial | Used By | What It Renders |
|---------|---------|----------------|
| `f1/_partials/header.twig` | `_layout.twig` | Navigation bar with site switcher |
| `f1/_partials/footer.twig` | `_layout.twig` | Footer with admin action buttons |
| `f1/_partials/playerStanding.twig` | `standings.twig`, `index.twig` | One row in the standings table |
| `f1/_partials/raceHistoryRow.twig` | `player-profile.twig`, `playerStanding.twig` | One row of race history (prediction + result) |
| `f1/_partials/raceCards/_card.twig` | `raceCards/*.twig` | Unified race card with status-driven styling |
| `f1/_partials/raceCards/upcoming.twig` | `race-list.twig` | Thin wrapper for upcoming race card |
| `f1/_partials/raceCards/selection_open.twig` | `race-list.twig` | Thin wrapper for open selection card |
| `f1/_partials/raceCards/selection_closed.twig` | `race-list.twig` | Thin wrapper for closed selection card |
| `f1/_partials/raceCards/completed.twig` | `race-list.twig` | Cached wrapper for completed race card |

### Macros (Reusable UI Components)

| Macro File | What It Contains |
|------------|-----------------|
| `f1/_macros/icons.twig` | SVG icon macros (calendar, clock, trophy, flag, users, check, etc.) — call with `{{ icons.trophy() }}` |
| `f1/_macros/ui.twig` | UI component macros (backButton, statusBadge, raceDateTime, statCard, linkButton, pendingBadge, pointsDisplay) |

**How to use macros in a template:**
```twig
{% import "f1/_macros/icons.twig" as icons %}
{% import "f1/_macros/ui.twig" as ui %}

{{ icons.trophy('w-5 h-5 text-amber-500') }}
{{ ui.statusBadge(race.raceStatus) }}
{{ ui.statCard('Total Points', player.totalPoints, icons.star()) }}
```

---

## JavaScript — Interactive Features

JavaScript lives in `src/js/`. The entry point `app.js` lazy-loads feature modules only when their DOM elements exist on the page.

### How It Works

`app.js` runs on `DOMContentLoaded` and checks for specific elements:

```javascript
// Only loads driverSelection.js if #driverGrid exists on the page
const driverGrid = document.querySelector('#driverGrid');
if (driverGrid) {
    import('@js/parts/driverSelection').then(m => m.driverSelection(driverGrid));
}
```

This means JavaScript is only loaded for pages that need it.

### Feature Modules

| File | Trigger Element | What It Does |
|------|----------------|-------------|
| `parts/driverSelection.js` | `#driverGrid` | Driver card click → confirm modal → POST prediction → reload. Also handles booster toggle. |
| `parts/seasonChart.js` | `#seasonChart` | Fetches `/leaderboard/season-chart`, renders Chart.js line chart with cumulative points per player. |
| `parts/skipPlayer.js` | `#skipPlayerBtn` | Confirm modal → POST skip → toast notification → reload. |
| `parts/refetchResults.js` | `#refetchResultsBtn` | POST to `/race/fetch-results/<id>` → shows loading spinner → toast. |
| `parts/adminActions.js` | `.js-admin-action` | Generic handler for admin footer buttons (sync drivers, sync races, fetch results). |
| `parts/playerRaceByRace.js` | `.js-player-race-by-race` | Accordion-style expandable race history rows. |
| `parts/a11y-dialog.js` | `[data-a11y-dialog]` | Accessible modal dialogs (login modal). |
| `parts/pageHeader.js` | — | Headroom.js sticky header + season site switcher. |
| `parts/formie.js` | — | Craft Formie form integration. |

### Building JavaScript

```bash
# Development (with hot reload)
ddev npm run dev

# Production build (outputs to web/dist/)
ddev npm run build
```

The built files go to `web/dist/` and are git-tracked so the production server doesn't need Node.js.

---

## CSS & Styling

Uses **Tailwind CSS 4** with component-specific CSS files.

### Structure

```
src/css/
├── app.css                     ← Main entry (imports Tailwind + components)
└── 3_components/
    ├── f1/                     ← F1 game-specific styles
    │   ├── f1-F1DriverCard.css ← Driver card states (hover, selected, confirming)
    │   ├── f1-cards.css        ← Generic card styling
    │   ├── f1-badges.css       ← Status badges, points displays
    │   ├── f1-login.css        ← Login form
    │   └── f1-table.css        ← Results/standings tables
    ├── globals/                ← Site-wide styles
    │   ├── header.css
    │   ├── footer.css
    │   ├── buttons.css
    │   └── headroom.css
    └── general.css             ← Typography, spacing, utilities
```

### Tailwind v4 Notes

Tailwind CSS 4 uses a different syntax than v3:
- Important modifier goes **after**: `cursor-pointer!` (not `!cursor-pointer`)
- Some utilities have changed names

---

## Database & Content Structure

The database is managed by Craft CMS. Content is organized into **Sections** (like database tables) with **Fields** (like columns).

### Sections

| Section | What It Stores | Key Fields |
|---------|----------------|------------|
| **drivers** | F1 driver roster | `driverId`, `driverCode`, `driverFirstName`, `driverLastName`, `driverNumber`, `teamName`, `isActive`, `driverPhoto` |
| **races** | Race schedule | `raceDate`, `raceRound`, `season`, `raceStatus`, `raceResults` (table field) |
| **players** | Player accounts | `playerEmail`, `totalPoints`, `currentStanding`, `previousStanding` |
| **predictions** | Individual predictions | `driverId`, `driverCode`, `driverName`, `selectionOrder`, `actualPosition`, `pointsEarned`, `boosterUsed` |

### Relationships

Predictions link to both a player and a race using Craft's **Entries** relation fields:

```
prediction.predictionPlayer → player entry
prediction.predictionRace   → race entry
```

### The `raceResults` Table Field

The `raceResults` field on races stores the full race classification as a **table field** (array of rows). Each row has:

```
position, driverCode, driverId, status (Finished/DNF/DSQ)
```

This is populated when results are fetched from the Jolpica API.

---

## API Endpoints Reference

All return JSON. Endpoints marked "Auth: player" require a logged-in player. "Auth: admin" also works for CP-logged-in admins.

### Prediction Endpoints

| Endpoint | Method | Auth | Request Body / Query | Response |
|----------|--------|------|---------------------|----------|
| `/prediction/submit` | POST | player/admin | `raceId`, `driverId`, `boosterUsed` | `{ success, prediction: { id, driverCode, driverName, selectionOrder, boosterUsed } }` |
| `/prediction/available-drivers` | GET | any | `?raceId=123` | `{ success, drivers: [{ id, driverId, driverCode, firstName, lastName, teamName, photo }] }` |
| `/prediction/selection-status` | GET | any | `?raceId=123` | `{ success, status, totalPlayers, selectedCount, currentSelector, isPlayerTurn }` |
| `/prediction/skip` | POST | player/admin | `raceId` | `{ success, skippedPlayer, selectionOrder, totalPlayers }` |

### Race Endpoints

| Endpoint | Method | Auth | Response |
|----------|--------|------|----------|
| `/race/fetch-results/<raceId>` | GET | any | `{ success, message }` |
| `/race/calculate-points/<raceId>` | GET | any | `{ success, message }` |

### Leaderboard Endpoints

| Endpoint | Method | Auth | Response |
|----------|--------|------|----------|
| `/leaderboard/standings` | GET | any | `{ success, season, standings: [{ position, name, totalPoints, positionChange, lastRace }] }` |
| `/leaderboard/race-breakdown/<raceId>` | GET | any | `{ success, race, breakdown, actualP10, perfectCount }` |
| `/leaderboard/season-chart` | GET | any | `{ success, labels, datasets }` |

### Sync Endpoints

| Endpoint | Method | Auth | Response |
|----------|--------|------|----------|
| `/update/sync-drivers` | POST | player | `{ success, message, imported, updated }` |
| `/update/sync-races` | POST | player | `{ success, message, imported, updated }` |
| `/update/fetch-results` | POST | player | `{ success, message, processed, raceNames }` |

---

## Caching

### Server-Side (PHP)

Uses Craft's cache system with **tag-based invalidation** via `CacheService`.

**How it works:** When you cache something, you tag it (e.g., `TAG_STANDINGS`). When standings change, you invalidate that tag, which clears all caches with that tag.

```php
// Caching data:
CacheService::getOrSet('cacheKey', [CacheService::TAG_STANDINGS], CacheService::DURATION_MEDIUM, function() {
    return expensiveCalculation();
});

// Invalidating after a prediction:
CacheService::invalidateAfterPrediction();  // Clears predictions + standings + chart caches
```

### Template-Level (Twig)

Some templates use Craft's `{% cache %}` tag for expensive template blocks:

```twig
{% cache using key "standings-" ~ currentSite.id for 1 hour %}
    {# Expensive template rendering here #}
{% endcache %}
```

### Browser-Level (HTTP Headers)

API endpoints set `Cache-Control` headers for browser/CDN caching:
- Active data (standings): 5 min cache, 1 hour stale-while-revalidate
- Completed data (race results): 1 hour cache, 1 week stale-while-revalidate

---

## Multi-Site / Multi-Season

The game supports **multiple F1 seasons** using Craft's multi-site feature. Each season is a separate Craft "site" within a site group.

### How It Works

- Sites are named by handle: `season2024`, `season2025`, `season2026`
- Each site has its own set of drivers, races, players, and predictions
- Players are cloned across seasons (same email, separate entries)
- The year is extracted from the site handle: `season2026` → `2026`

### Site Switcher

The header includes a season switcher dropdown that lets users navigate between seasons. This is powered by `Module::getAvailableSites()`.

### Starting a New Season

```bash
# 1. Create a new site in Craft CP (Settings → Sites) with handle "season2027"

# 2. Clone players from previous season
ddev craft f1-gooat/import/clone-players --site=season2027 --from=season2026

# 3. Import drivers for the new year
ddev craft f1-gooat/import/drivers --site=season2027

# 4. Import race schedule
ddev craft f1-gooat/import/races --site=season2027 --year=2027

# 5. Update team info
ddev craft f1-gooat/import/update-teams --site=season2027
```

---

## Admin Features

When a Craft CMS admin is logged into the **Control Panel** (`/admin`), they get extra powers on the frontend:

### Admin Voting

Admins can vote **on behalf of any player** without logging in as that player. When an admin visits the voting page:

- A blue **"Admin Mode — Voting for [Player Name]"** banner appears
- The driver grid is fully interactive
- Predictions are submitted on behalf of the current selector
- Boosters can be toggled for the player being voted for
- Skipping works without needing a player login

This is useful when a player can't access the site themselves (e.g., they tell you their pick over text).

### Admin Sync Buttons

The footer shows admin action buttons:
- **Sync Drivers** — Pulls latest driver roster from the Jolpica API
- **Sync Races** — Pulls latest race schedule
- **Fetch Results** — Fetches results for all races waiting for results

### Admin Detection

Admin status is checked via:
```php
$isAdmin = Craft::$app->getUser()->getIdentity() && Craft::$app->getUser()->getIdentity()->admin;
```

This checks the **Craft CP login**, not the player email login. They are separate authentication systems.

---

## Environment Variables

Set these in your `.env` file (not committed to git):

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `JOLPICA_API_URL` | No | F1 data API base URL | `https://api.jolpi.ca/ergast/f1` (default) |
| `CRAFT_APP_ID` | Yes | Unique Craft app identifier | `gooat` |
| `CRAFT_DEV_MODE` | No | Enable dev features | `true` |
| `PRIMARY_SITE_URL` | Yes | Base site URL | `https://gooat.ddev.site` |
| `CRAFT_DB_SERVER` | Yes | Database host | `db` (DDEV default) |
| `CRAFT_DB_DATABASE` | Yes | Database name | `db` (DDEV default) |
| `CRAFT_DB_USER` | Yes | Database user | `db` (DDEV default) |
| `CRAFT_DB_PASSWORD` | Yes | Database password | `db` (DDEV default) |
| `SMTP_HOST` | No | Mail server host | `localhost` |
| `SMTP_PORT` | No | Mail server port | `1025` |

---

## Common Tasks & How-Tos

### "I want to change the points system"

Edit `modules/f1gooat/PointsCalculator.php`. Change the `POINTS_MAP` array. The key is the distance from P10, the value is the points awarded.

### "I want to add a new page"

1. Create a Twig template in `templates/f1/your-page.twig` (extend `f1/_layout`)
2. Add a controller action in the appropriate controller (or `FrontendController.php`)
3. Register the route in `Module.php` → `init()` → `EVENT_REGISTER_SITE_URL_RULES`

### "I want to change the draft order"

Edit `modules/f1gooat/SelectionService.php` → `getCurrentSelector()`. Currently it reverses the standings. You could change it to random, alphabetical, or any custom order.

### "I want to add a new field to drivers/races/players"

1. Add the field in Craft CP → Settings → Fields
2. Add it to the appropriate entry type in Settings → Sections → Entry Types
3. The field will automatically be available in templates as `entry.fieldHandle`

### "I want to change how the site looks"

- **Layout/structure:** Edit templates in `templates/f1/`
- **Colors/spacing:** Most styling is Tailwind utility classes directly in templates
- **Component styles:** Edit CSS files in `src/css/3_components/`
- **After CSS changes:** Run `ddev npm run build` (or `ddev npm run dev` for live reload)

### "Race results aren't fetching automatically"

Results are fetched via the Jolpica API. The race must be in `selection_closed` status. You can:
1. Click "Fetch Results" in the admin footer
2. Visit `/race/fetch-results/<raceId>` directly
3. Run `ddev craft f1-gooat/cron/fetch-results` from the terminal

### "I need to reset points for a race"

Visit `/race/calculate-points/<raceId>` — this recalculates all points from the stored race results without re-fetching from the API.

### "I want to add a new player"

1. Go to Craft CP → Entries → Players
2. Create a new entry on the correct site (season)
3. Set their `playerEmail` — this is what they'll use to log in

### "I want to force-open voting for a race"

1. Go to Craft CP → Entries → Races
2. Find the race, change `raceStatus` to `selection_open`
3. Save

---

## External APIs

### Jolpica F1 API

Free F1 statistics API (successor to Ergast). Used for:
- Driver roster and team info
- Race schedule with dates/times
- Race results (finishing positions)

**Base URL:** `https://api.jolpi.ca/ergast/f1`

**Endpoints we use:**
| Endpoint | What It Returns |
|----------|----------------|
| `/{year}/drivers/` | All drivers for a season |
| `/{year}/driverStandings/` | Driver standings (includes team info) |
| `/{year}/` | Full race schedule |
| `/{year}/{round}/results/` | Race results for a specific round |

No API key required. Rate limiting applies (be gentle with bulk requests).
