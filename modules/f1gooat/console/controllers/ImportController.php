<?php

namespace modules\f1gooat\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use GuzzleHttp\Client;
use yii\console\ExitCode;
use modules\f1gooat\Module;

class ImportController extends Controller
{
    /**
     * @var string|null The site handle to import into (e.g. season2026). Required.
     */
    public ?string $site = null;

    /**
     * @var int|null The season year to import (defaults to year derived from site handle)
     */
    public ?int $year = null;

    /**
     * @var string|null Source site handle for clone-players (e.g. season2025)
     */
    public ?string $from = null;

    /**
     * @var string|null Path to JSON file for predictions import
     */
    public ?string $file = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'site';
        if ($actionID === 'races') {
            $options[] = 'year';
        }
        if ($actionID === 'clone-players') {
            $options[] = 'from';
        }
        if ($actionID === 'predictions') {
            $options[] = 'file';
        }
        return $options;
    }

    /**
     * Resolve the site ID from the --site handle
     */
    private function resolveSiteId(): int
    {
        if (!$this->site) {
            throw new \Exception('--site is required (e.g. --site=season2026)');
        }

        $site = Craft::$app->getSites()->getSiteByHandle($this->site);
        if (!$site) {
            throw new \Exception("Site '{$this->site}' not found");
        }

        return $site->id;
    }

    /**
     * Get the year from --year option or derive from site handle
     */
    private function resolveYear(): int
    {
        if ($this->year) {
            return $this->year;
        }
        if ($this->site) {
            return (int) str_replace('season', '', $this->site);
        }
        return (int) date('Y');
    }

    /**
     * Import drivers from Jolpica API.
     * Usage: php craft f1-gooat/import/drivers --site=season2026
     */
    public function actionDrivers(): int
    {
        $siteId = $this->resolveSiteId();
        $year = $this->resolveYear();
        $apiBase = Module::getApiBaseUrl();

        echo "Importing F1 drivers for {$year} into site '{$this->site}'...\n";

        $client = new Client();

        try {
            $response = $client->get("{$apiBase}/{$year}/drivers.json?limit=100");
            $data = json_decode($response->getBody(), true);

            $drivers = $data['MRData']['DriverTable']['Drivers'] ?? [];

            if (empty($drivers)) {
                echo "No drivers found.\n";
                return ExitCode::OK;
            }

            $section = Craft::$app->getEntries()->getSectionByHandle('drivers');
            $entryType = $section->getEntryTypes()[0];

            foreach ($drivers as $driverData) {
                $driverId = $driverData['driverId'];

                // Check if driver already exists on this site
                $existing = Entry::find()
                    ->section('drivers')
                    ->siteId($siteId)
                    ->driverId($driverId)
                    ->one();

                if ($existing) {
                    echo "Driver {$driverId} already exists, skipping.\n";
                    continue;
                }

                // Create new driver entry on the target site
                $driver = new Entry();
                $driver->sectionId = $section->id;
                $driver->typeId = $entryType->id;
                $driver->siteId = $siteId;
                $driver->authorId = 1;

                $driver->setFieldValues([
                    'driverId' => $driverId,
                    'driverCode' => $driverData['code'] ?? '',
                    'driverFirstName' => $driverData['givenName'] ?? '',
                    'driverLastName' => $driverData['familyName'] ?? '',
                    'teamName' => '',
                    'isActive' => true,
                ]);

                if (Craft::$app->getElements()->saveElement($driver)) {
                    $code = $driverData['code'] ?? 'N/A';
                    echo "Imported {$driverData['givenName']} {$driverData['familyName']} ({$code})\n";
                } else {
                    echo "Failed to import {$driverId}: " . json_encode($driver->getErrors()) . "\n";
                }
            }

            echo "\nDriver import complete!\n";
            return ExitCode::OK;

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Import race schedule from Jolpica API.
     * Usage: php craft f1-gooat/import/races --site=season2026 --year=2026
     */
    public function actionRaces(): int
    {
        $siteId = $this->resolveSiteId();
        $year = $this->resolveYear();
        $apiBase = Module::getApiBaseUrl();

        echo "Importing F1 race schedule for {$year} into site '{$this->site}'...\n";

        $client = new Client();

        try {
            $response = $client->get("{$apiBase}/{$year}/");
            $data = json_decode($response->getBody(), true);

            $races = $data['MRData']['RaceTable']['Races'] ?? [];

            if (empty($races)) {
                echo "No races found.\n";
                return ExitCode::OK;
            }

            $section = Craft::$app->getEntries()->getSectionByHandle('races');
            $entryType = $section->getEntryTypes()[0];

            foreach ($races as $raceData) {
                $round = $raceData['round'];

                // Check if race already exists on this site
                $existing = Entry::find()
                    ->section('races')
                    ->siteId($siteId)
                    ->raceRound($round)
                    ->one();

                if ($existing) {
                    echo "Race round {$round} already exists, skipping.\n";
                    continue;
                }

                // Parse date
                $raceDateTime = new \DateTime($raceData['date'] . ' ' . ($raceData['time'] ?? '00:00:00'));

                // Create new race entry on the target site
                $race = new Entry();
                $race->sectionId = $section->id;
                $race->typeId = $entryType->id;
                $race->siteId = $siteId;
                $race->authorId = 1;
                $race->title = $raceData['raceName'];

                $race->setFieldValues([
                    'raceDate' => $raceDateTime,
                    'raceRound' => (int)$round,
                    'season' => (int)$year,
                    'raceStatus' => 'upcoming',
                ]);

                if (Craft::$app->getElements()->saveElement($race)) {
                    echo "Imported {$raceData['raceName']} (Round {$round})\n";
                } else {
                    echo "Failed to import race: " . json_encode($race->getErrors()) . "\n";
                }
            }

            echo "\nRace schedule import complete!\n";
            return ExitCode::OK;

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Update driver team information from current constructors.
     * Usage: php craft f1-gooat/import/update-teams --site=season2026
     */
    public function actionUpdateTeams(): int
    {
        $siteId = $this->resolveSiteId();
        $year = $this->resolveYear();
        $apiBase = Module::getApiBaseUrl();

        echo "Updating driver team information for {$year} on site '{$this->site}'...\n";

        $client = new Client();

        try {
            $response = $client->get("{$apiBase}/{$year}/driverStandings/");
            $data = json_decode($response->getBody(), true);

            $standings = $data['MRData']['StandingsTable']['StandingsLists'][0]['DriverStandings'] ?? [];

            if (empty($standings)) {
                echo "No standings found.\n";
                return ExitCode::OK;
            }

            foreach ($standings as $standing) {
                $driverId = $standing['Driver']['driverId'];
                $teamName = $standing['Constructors'][0]['name'] ?? '';

                $driver = Entry::find()
                    ->section('drivers')
                    ->siteId($siteId)
                    ->driverId($driverId)
                    ->one();

                if ($driver) {
                    $driver->setFieldValue('teamName', $teamName);

                    if (Craft::$app->getElements()->saveElement($driver)) {
                        echo "Updated {$driverId} team to {$teamName}\n";
                    } else {
                        echo "Failed to update {$driverId}\n";
                    }
                }
            }

            echo "\nTeam update complete!\n";
            return ExitCode::OK;

        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Clone players from one site to another (for new season setup).
     * Usage: php craft f1-gooat/import/clone-players --site=season2026 --from=season2025
     */
    public function actionClonePlayers(): int
    {
        $targetSiteId = $this->resolveSiteId();

        if (!$this->from) {
            throw new \Exception('--from is required (e.g. --from=season2025)');
        }

        $sourceSite = Craft::$app->getSites()->getSiteByHandle($this->from);
        if (!$sourceSite) {
            throw new \Exception("Source site '{$this->from}' not found");
        }

        echo "Cloning players from '{$this->from}' to '{$this->site}'...\n";

        $sourcePlayers = Entry::find()
            ->section('players')
            ->siteId($sourceSite->id)
            ->all();

        if (empty($sourcePlayers)) {
            echo "No players found on source site.\n";
            return ExitCode::OK;
        }

        $section = Craft::$app->getEntries()->getSectionByHandle('players');
        $entryType = $section->getEntryTypes()[0];

        foreach ($sourcePlayers as $sourcePlayer) {
            // Check if player already exists on target site (by email)
            $existing = Entry::find()
                ->section('players')
                ->siteId($targetSiteId)
                ->playerEmail($sourcePlayer->playerEmail)
                ->one();

            if ($existing) {
                echo "Player '{$sourcePlayer->title}' already exists on target site, skipping.\n";
                continue;
            }

            $newPlayer = new Entry();
            $newPlayer->sectionId = $section->id;
            $newPlayer->typeId = $entryType->id;
            $newPlayer->siteId = $targetSiteId;
            $newPlayer->authorId = 1;
            $newPlayer->title = $sourcePlayer->title;

            $newPlayer->setFieldValues([
                'playerEmail' => $sourcePlayer->playerEmail,
                'totalPoints' => 0,
                'currentStanding' => 0,
                'previousStanding' => 0,
            ]);

            if (Craft::$app->getElements()->saveElement($newPlayer)) {
                echo "Cloned '{$sourcePlayer->title}'\n";
            } else {
                echo "Failed to clone '{$sourcePlayer->title}': " . json_encode($newPlayer->getErrors()) . "\n";
            }
        }

        echo "\nPlayer clone complete!\n";
        return ExitCode::OK;
    }

    /**
     * Import predictions from a JSON file.
     * Usage: php craft f1-gooat/import/predictions --site=season2025 --file=predictions.json
     *
     * JSON format:
     * [
     *   { "round": 1, "playerId": 123, "driverCode": "VER", "selectionOrder": 1, "boosterUsed": false },
     *   { "round": 1, "playerId": 456, "driverCode": "SKIP", "selectionOrder": 2, "boosterUsed": false }
     * ]
     */
    public function actionPredictions(): int
    {
        $siteId = $this->resolveSiteId();

        if (!$this->file) {
            throw new \Exception('--file is required (path to JSON file)');
        }

        $filePath = realpath($this->file);
        if (!$filePath || !file_exists($filePath)) {
            throw new \Exception("File not found: {$this->file}");
        }

        $json = file_get_contents($filePath);
        $predictions = json_decode($json, true);

        if (!is_array($predictions) || empty($predictions)) {
            throw new \Exception('Invalid or empty JSON file');
        }

        $section = Craft::$app->getEntries()->getSectionByHandle('predictions');
        $entryType = $section->getEntryTypes()[0];

        // Pre-load lookups
        $races = [];
        foreach (Entry::find()->section('races')->siteId($siteId)->all() as $race) {
            $races[(int)$race->raceRound] = $race;
        }

        $players = [];
        foreach (Entry::find()->section('players')->siteId($siteId)->all() as $player) {
            $players[$player->id] = $player;
        }

        $drivers = [];
        foreach (Entry::find()->section('drivers')->siteId($siteId)->all() as $driver) {
            if ($driver->driverCode) {
                $drivers[$driver->driverCode] = $driver;
            }
        }

        echo "Importing " . count($predictions) . " predictions into '{$this->site}'...\n\n";

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($predictions as $i => $row) {
            $line = $i + 1;
            $round = (int)($row['round'] ?? 0);
            $playerId = (int)($row['playerId'] ?? 0);
            $driverCode = $row['driverCode'] ?? '';
            $selectionOrder = (int)($row['selectionOrder'] ?? 0);
            $boosterUsed = (bool)($row['boosterUsed'] ?? false);

            // Validate required fields
            if (!$round || !$playerId || !$driverCode || !$selectionOrder) {
                echo "[{$line}] ERROR: Missing required fields (round, playerId, driverCode, selectionOrder)\n";
                $errors++;
                continue;
            }

            // Look up race
            if (!isset($races[$round])) {
                echo "[{$line}] ERROR: Race round {$round} not found\n";
                $errors++;
                continue;
            }
            $race = $races[$round];

            // Look up player
            if (!isset($players[$playerId])) {
                echo "[{$line}] ERROR: Player ID {$playerId} not found\n";
                $errors++;
                continue;
            }
            $player = $players[$playerId];

            // Check for duplicate
            $existing = Entry::find()
                ->section('predictions')
                ->siteId($siteId)
                ->relatedTo([
                    'and',
                    ['targetElement' => $race->id, 'field' => 'predictionRace'],
                    ['targetElement' => $player->id, 'field' => 'predictionPlayer'],
                ])
                ->one();

            if ($existing) {
                echo "[{$line}] SKIP: Prediction already exists for {$player->title} in round {$round}\n";
                $skipped++;
                continue;
            }

            // Handle skip vs normal prediction
            $isSkip = strtoupper($driverCode) === 'SKIP';
            $driverName = 'Skipped';
            $driverId = 'SKIP';

            if (!$isSkip) {
                if (!isset($drivers[$driverCode])) {
                    echo "[{$line}] ERROR: Driver '{$driverCode}' not found\n";
                    $errors++;
                    continue;
                }
                $driver = $drivers[$driverCode];
                $driverId = $driver->driverId;
                $driverName = trim($driver->driverFirstName . ' ' . $driver->driverLastName);
            }

            // Create prediction
            $prediction = new Entry();
            $prediction->sectionId = $section->id;
            $prediction->typeId = $entryType->id;
            $prediction->siteId = $siteId;
            $prediction->authorId = 1;

            $fieldValues = [
                'predictionRace' => [$race->id],
                'predictionPlayer' => [$player->id],
                'driverId' => $driverId,
                'driverCode' => $isSkip ? 'SKIP' : $driverCode,
                'driverName' => $driverName,
                'selectionOrder' => $selectionOrder,
                'boosterUsed' => $boosterUsed,
            ];

            if ($isSkip) {
                $fieldValues['pointsEarned'] = 0;
            }

            $prediction->setFieldValues($fieldValues);

            if (Craft::$app->getElements()->saveElement($prediction)) {
                $label = $isSkip ? 'SKIP' : $driverCode;
                echo "[{$line}] OK: Round {$round} — {$player->title} -> {$label}" . ($boosterUsed ? ' (BOOSTER)' : '') . "\n";
                $imported++;
            } else {
                echo "[{$line}] ERROR: Failed to save — " . json_encode($prediction->getErrors()) . "\n";
                $errors++;
            }
        }

        echo "\nImport complete! Imported: {$imported}, Skipped: {$skipped}, Errors: {$errors}\n";
        return $errors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}