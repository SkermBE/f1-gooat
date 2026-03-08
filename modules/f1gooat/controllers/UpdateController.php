<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use craft\helpers\Queue;
use yii\web\Response;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\jobs\FetchRaceResultsJob;
use modules\f1gooat\PointsCalculator;

class UpdateController extends Controller
{
    protected array|int|bool $allowAnonymous = ['sync-drivers', 'sync-races', 'fetch-all-results'];

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
     * Sync drivers from Jolpica API for the current site/season.
     * Imports new drivers and updates team info.
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
                        // Update existing driver fields
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
                    // Update title and date for existing races
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
                    'raceStatus' => 'upcoming',
                ]);

                if (Craft::$app->getElements()->saveElement($race)) {
                    $imported++;
                }
            }

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
     * Runs synchronously (no queue) so results and next-race opening happen immediately.
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
                ->raceStatus('selection_closed')
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
                // Fetch from Jolpica API
                $url = "{$apiBase}/{$race->season}/{$race->raceRound}/results/";
                $response = $client->get($url);
                $data = json_decode($response->getBody(), true);
                $results = $data['MRData']['RaceTable']['Races'][0]['Results'] ?? [];

                if (empty($results)) {
                    continue;
                }

                // Format and save results
                $formattedResults = $this->formatResults($results);
                $race->setFieldValue('raceResults', $formattedResults);
                $race->setFieldValue('raceStatus', 'completed');
                Craft::$app->getElements()->saveElement($race);

                // Calculate points for predictions
                $this->calculatePoints($race, $siteId);

                // Update player standings
                $this->updatePlayerStandings($siteId);

                // Auto-open next race
                $this->openNextRace($race, $siteId);

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

    private function formatResults(array $apiResults): array
    {
        $formatted = [];
        foreach ($apiResults as $result) {
            $status = $result['status'] ?? '';
            $isFinished = $status === 'Finished' ||
                         strpos($status, '+') === 0 ||
                         strpos($status, 'Lap') !== false;

            if ($isFinished) {
                $displayStatus = 'Finished';
            } elseif (stripos($status, 'Disqualified') !== false) {
                $displayStatus = 'DSQ';
            } else {
                $displayStatus = 'DNF';
            }

            $formatted[] = [
                'position' => (int)$result['position'],
                'driverCode' => $result['Driver']['code'] ?? '',
                'driverId' => $result['Driver']['driverId'] ?? '',
                'status' => $displayStatus,
            ];
        }
        return $formatted;
    }

    private function calculatePoints(Entry $race, int $siteId): void
    {
        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $race->id, 'field' => 'predictionRace'])
            ->all();

        foreach ($predictions as $prediction) {
            $driverCode = $prediction->driverCode;
            $result = null;
            foreach ($race->raceResults as $r) {
                if ($r['driverCode'] === $driverCode) {
                    $result = $r;
                    break;
                }
            }

            if (!$result || $result['status'] !== 'Finished') {
                $prediction->setFieldValue('actualPosition', null);
                $prediction->setFieldValue('pointsEarned', 0);
            } else {
                $actualPosition = (int)$result['position'];
                $points = PointsCalculator::calculate($actualPosition);
                $prediction->setFieldValue('actualPosition', $actualPosition);
                $prediction->setFieldValue('pointsEarned', $points);
            }

            Craft::$app->getElements()->saveElement($prediction);
        }
    }

    private function updatePlayerStandings(int $siteId): void
    {
        $players = Entry::find()->section('players')->siteId($siteId)->all();

        foreach ($players as $player) {
            $player->setFieldValue('previousStanding', $player->currentStanding ?? 0);
        }

        foreach ($players as $player) {
            $predictions = Entry::find()
                ->section('predictions')
                ->siteId($siteId)
                ->relatedTo(['targetElement' => $player->id, 'field' => 'predictionPlayer'])
                ->all();

            $totalPoints = 0;
            foreach ($predictions as $prediction) {
                $totalPoints += $prediction->pointsEarned ?? 0;
            }
            $player->setFieldValue('totalPoints', $totalPoints);
        }

        usort($players, fn($a, $b) => ($b->totalPoints ?? 0) <=> ($a->totalPoints ?? 0));

        foreach ($players as $index => $player) {
            $player->setFieldValue('currentStanding', $index + 1);
            Craft::$app->getElements()->saveElement($player);
        }
    }

    private function openNextRace(Entry $completedRace, int $siteId): void
    {
        $nextRace = Entry::find()
            ->section('races')
            ->siteId($siteId)
            ->raceRound($completedRace->raceRound + 1)
            ->one();

        if ($nextRace && $nextRace->raceStatus == 'upcoming') {
            $nextRace->setFieldValue('raceStatus', 'selection_open');
            Craft::$app->getElements()->saveElement($nextRace);
        }
    }
}
