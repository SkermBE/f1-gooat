<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\PointsCalculator;

class RaceController extends Controller
{
    protected array|int|bool $allowAnonymous = ['fetch-race-results', 'calculate-points'];

    /**
     * Fetch race results from Jolpica API and calculate points instantly
     */
    public function actionFetchRaceResults(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->siteId('*')->one();

        if (!$race) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race not found',
            ]);
        }

        if ($race->raceStatus != 'selection_closed' && $race->raceStatus != 'completed') {
            return $this->asJson([
                'success' => false,
                'error' => 'Race is not ready for results fetching',
            ]);
        }

        $siteId = $race->siteId;

        try {
            // Fetch from Jolpica API
            $client = new Client();
            $apiBase = Module::getApiBaseUrl();
            $url = "{$apiBase}/{$race->season}/{$race->raceRound}/results/";

            $response = $client->get($url);
            $data = json_decode($response->getBody(), true);

            $results = $data['MRData']['RaceTable']['Races'][0]['Results'] ?? [];

            if (empty($results)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No results found in API response',
                ]);
            }

            // Format and save results
            $formattedResults = $this->formatResults($results);
            $race->setFieldValue('raceResults', $formattedResults);
            $race->setFieldValue('raceStatus', 'completed');

            if (!Craft::$app->getElements()->saveElement($race)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Could not save race results',
                ]);
            }

            // Calculate points
            $this->calculatePointsForRace($race, $siteId);

            // Update standings
            $this->updatePlayerStandings($siteId);

            // Auto-open next race
            $this->openNextRace($race);

            return $this->asJson([
                'success' => true,
                'message' => 'Results fetched and points calculated',
            ]);

        } catch (\Exception $e) {
            Craft::error('Failed to fetch race results: ' . $e->getMessage(), __METHOD__);
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to fetch results: ' . $e->getMessage(),
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

    /**
     * Recalculate points from existing race results
     */
    public function actionCalculatePoints(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->siteId('*')->one();

        if (!$race || !$race->raceResults) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race results not available',
            ]);
        }

        $this->calculatePointsForRace($race, $race->siteId);
        $this->updatePlayerStandings($race->siteId);

        return $this->asJson([
            'success' => true,
            'message' => 'Points recalculated successfully',
        ]);
    }

    private function calculatePointsForRace(Entry $race, int $siteId): void
    {
        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $race->id, 'field' => 'predictionRace'])
            ->all();

        foreach ($predictions as $prediction) {
            $result = $this->findDriverResult($race->raceResults, $prediction->driverCode);

            if (!$result || $result['status'] !== 'Finished') {
                $prediction->setFieldValue('actualPosition', null);
                $prediction->setFieldValue('pointsEarned', 0);
            } else {
                $actualPosition = (int)$result['position'];
                $points = PointsCalculator::calculate($actualPosition);

                if ($prediction->boosterUsed) {
                    $points *= 2;
                }

                $prediction->setFieldValue('actualPosition', $actualPosition);
                $prediction->setFieldValue('pointsEarned', $points);
            }

            Craft::$app->getElements()->saveElement($prediction);
        }
    }

    /**
     * Find driver result in race results table
     */
    private function findDriverResult(array $results, string $driverCode): ?array
    {
        foreach ($results as $result) {
            if ($result['driverCode'] === $driverCode) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Auto-open selection for the next race after completion
     */
    private function openNextRace(Entry $completedRace): void
    {
        $nextRace = Entry::find()
            ->section('races')
            ->siteId($completedRace->siteId)
            ->raceRound($completedRace->raceRound + 1)
            ->one();

        if ($nextRace && $nextRace->raceStatus == 'upcoming') {
            $nextRace->setFieldValue('raceStatus', 'selection_open');
            Craft::$app->getElements()->saveElement($nextRace);
        }
    }

    /**
     * Update all player standings after race completion (site-scoped)
     */
    private function updatePlayerStandings(int $siteId): void
    {
        $players = Entry::find()->section('players')->siteId($siteId)->all();

        // Get all race IDs for this site/season
        $seasonRaces = Entry::find()
            ->section('races')
            ->siteId($siteId)
            ->all();
        $seasonRaceIds = array_map(fn($r) => $r->id, $seasonRaces);

        // Store previous standings
        foreach ($players as $player) {
            $player->setFieldValue('previousStanding', $player->currentStanding ?? 0);
        }

        // Calculate season totals
        foreach ($players as $player) {
            $predictions = Entry::find()
                ->section('predictions')
                ->siteId($siteId)
                ->relatedTo([
                    'and',
                    ['targetElement' => $player->id, 'field' => 'predictionPlayer'],
                    ['targetElement' => $seasonRaceIds, 'field' => 'predictionRace'],
                ])
                ->all();

            $totalPoints = 0;
            foreach ($predictions as $prediction) {
                $totalPoints += $prediction->pointsEarned ?? 0;
            }

            $player->setFieldValue('totalPoints', $totalPoints);
        }

        // Sort players by points
        usort($players, function($a, $b) {
            return ($b->totalPoints ?? 0) <=> ($a->totalPoints ?? 0);
        });

        // Assign new standings
        foreach ($players as $index => $player) {
            $player->setFieldValue('currentStanding', $index + 1);
            Craft::$app->getElements()->saveElement($player);
        }
    }
}