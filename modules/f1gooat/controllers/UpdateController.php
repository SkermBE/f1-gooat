<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\RaceStatus;
use modules\f1gooat\RaceResultsService;
use modules\f1gooat\CacheService;

class UpdateController extends Controller
{
    protected array|int|bool $allowAnonymous = ['sync-drivers', 'sync-races', 'fetch-all-results', 'clear-cache'];

    private function requirePlayer(): ?Response
    {
        if (!Module::getCurrentPlayer()) {
            return $this->asJson(['success' => false, 'error' => 'Not logged in']);
        }
        return null;
    }

    private function getSiteId(): int
    {
        return Craft::$app->getSites()->getCurrentSite()->id;
    }

    private function getSeasonYear(): int
    {
        return Module::getCurrentSeasonYear();
    }

    /**
     * Clear all caches by running the Craft console command. Available to anyone.
     */
    public function actionClearCache(): Response
    {
        $craftPath = Craft::getAlias('@root') . '/craft';
        exec("php {$craftPath} clear-caches/all 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            return $this->asJson([
                'success' => false,
                'error' => implode(' ', $output),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'message' => 'Cache cleared.',
        ]);
    }

    /**
     * Sync drivers from Jolpica API for the current site/season.
     */
    public function actionSyncDrivers(): Response
    {
        if ($denied = $this->requirePlayer()) return $denied;

        $siteId = $this->getSiteId();
        $year = $this->getSeasonYear();
        $apiBase = Module::getApiBaseUrl();
        $client = new Client();

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        try {
            // 1. Import new drivers
            $response = $client->get("{$apiBase}/{$year}/drivers/");
            $data = json_decode($response->getBody(), true);
            $drivers = $data['MRData']['DriverTable']['Drivers'] ?? [];

            if (!empty($drivers)) {
                $section = Craft::$app->getEntries()->getSectionByHandle('drivers');
                $entryType = $section->getEntryTypes()[0];

                foreach ($drivers as $driverData) {
                    $driverId = $driverData['driverId'];

                    $existing = Entry::find()
                        ->section('drivers')
                        ->siteId($siteId)
                        ->driverId($driverId)
                        ->one();

                    if ($existing) {
                        $existing->setFieldValues([
                            'driverCode' => $driverData['code'] ?? $existing->driverCode,
                            'driverFirstName' => $driverData['givenName'] ?? $existing->driverFirstName,
                            'driverLastName' => $driverData['familyName'] ?? $existing->driverLastName,
                            'driverNumber' => $driverData['permanentNumber'] ?? $existing->driverNumber,
                        ]);
                        if (Craft::$app->getElements()->saveElement($existing)) {
                            $updated++;
                        }
                        continue;
                    }

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
                        'driverNumber' => $driverData['permanentNumber'] ?? '',
                        'teamName' => '',
                        'isActive' => true,
                    ]);

                    if (Craft::$app->getElements()->saveElement($driver)) {
                        $imported++;
                    }
                }
            }

            // 2. Update team info from standings
            $response = $client->get("{$apiBase}/{$year}/driverStandings/");
            $data = json_decode($response->getBody(), true);
            $standings = $data['MRData']['StandingsTable']['StandingsLists'][0]['DriverStandings'] ?? [];

            foreach ($standings as $standing) {
                $driverId = $standing['Driver']['driverId'];
                $teamName = $standing['Constructors'][0]['name'] ?? '';

                $driver = Entry::find()
                    ->section('drivers')
                    ->siteId($siteId)
                    ->driverId($driverId)
                    ->one();

                if ($driver && $driver->teamName !== $teamName) {
                    $driver->setFieldValue('teamName', $teamName);
                    if (Craft::$app->getElements()->saveElement($driver)) {
                        $updated++;
                    }
                }
            }

            CacheService::invalidateAfterDriverSync();

            return $this->asJson([
                'success' => true,
                'message' => "Drivers synced: {$imported} imported, {$updated} updated, {$skipped} skipped.",
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync race schedule from Jolpica API for the current site/season.
     */
    public function actionSyncRaces(): Response
    {
        if ($denied = $this->requirePlayer()) return $denied;

        $siteId = $this->getSiteId();
        $year = $this->getSeasonYear();
        $apiBase = Module::getApiBaseUrl();
        $client = new Client();

        $imported = 0;
        $updated = 0;

        try {
            $response = $client->get("{$apiBase}/{$year}/");
            $data = json_decode($response->getBody(), true);
            $races = $data['MRData']['RaceTable']['Races'] ?? [];

            if (empty($races)) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'No races found in API.',
                    'imported' => 0,
                    'updated' => 0,
                ]);
            }

            $section = Craft::$app->getEntries()->getSectionByHandle('races');
            $entryType = $section->getEntryTypes()[0];

            foreach ($races as $raceData) {
                $round = $raceData['round'];
                $raceDateTime = new \DateTime($raceData['date'] . ' ' . ($raceData['time'] ?? '00:00:00'));

                $existing = Entry::find()
                    ->section('races')
                    ->siteId($siteId)
                    ->raceRound($round)
                    ->one();

                if ($existing) {
                    $existing->title = $raceData['raceName'];
                    $existing->setFieldValues([
                        'raceDate' => $raceDateTime,
                        'season' => (int)$year,
                    ]);
                    if (Craft::$app->getElements()->saveElement($existing)) {
                        $updated++;
                    }
                    continue;
                }

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
                    'raceStatus' => RaceStatus::UPCOMING,
                ]);

                if (Craft::$app->getElements()->saveElement($race)) {
                    $imported++;
                }
            }

            CacheService::invalidateAfterRaceSync();

            return $this->asJson([
                'success' => true,
                'message' => "Races synced: {$imported} imported, {$updated} updated.",
                'imported' => $imported,
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch results for all races with status 'selection_closed' on the current site.
     */
    public function actionFetchAllResults(): Response
    {
        if ($denied = $this->requirePlayer()) return $denied;

        $siteId = $this->getSiteId();
        $apiBase = Module::getApiBaseUrl();
        $client = new Client();

        try {
            $races = Entry::find()
                ->section('races')
                ->siteId($siteId)
                ->raceStatus(RaceStatus::SELECTION_CLOSED)
                ->all();

            if (empty($races)) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'No races ready for results fetching.',
                    'processed' => 0,
                ]);
            }

            $processed = 0;
            $raceNames = [];

            foreach ($races as $race) {
                $url = "{$apiBase}/{$race->season}/{$race->raceRound}/results/";
                $response = $client->get($url);
                $data = json_decode($response->getBody(), true);
                $results = $data['MRData']['RaceTable']['Races'][0]['Results'] ?? [];

                if (empty($results)) {
                    continue;
                }

                $formattedResults = RaceResultsService::formatResults($results);
                RaceResultsService::processRaceResults($race, $formattedResults);

                $processed++;
                $raceNames[] = $race->title;
            }

            return $this->asJson([
                'success' => true,
                'message' => "Processed results for {$processed} race(s): " . implode(', ', $raceNames),
                'processed' => $processed,
                'raceNames' => $raceNames,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
