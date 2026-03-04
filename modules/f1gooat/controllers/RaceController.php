<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use craft\helpers\Queue;
use modules\f1gooat\jobs\FetchRaceResultsJob;
use modules\f1gooat\PointsCalculator;

class RaceController extends Controller
{
    protected array|int|bool $allowAnonymous = ['fetch-race-results'];

    /**
     * Fetch race results from Jolpica API
     */
    public function actionFetchRaceResults(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->one();

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

        // Queue job to fetch results
        Queue::push(new FetchRaceResultsJob([
            'raceId' => $raceId,
        ]));

        return $this->asJson([
            'success' => true,
            'message' => 'Results fetching queued',
        ]);
    }

    /**
     * Process fetched results and calculate points
     */
    public function actionCalculatePoints(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->one();

        if (!$race || !$race->raceResults) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race results not available',
            ]);
        }

        $predictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->all();

        foreach ($predictions as $prediction) {
            $result = $this->findDriverResult($race->raceResults, $prediction->driverId);

            if (!$result || $result['status'] != 'Finished') {
                // DNF or not found
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

        // Update race status
        $race->setFieldValue('raceStatus', 'completed');
        Craft::$app->getElements()->saveElement($race);

        // Update player standings for this site/season
        $this->updatePlayerStandings($race->siteId);

        return $this->asJson([
            'success' => true,
            'message' => 'Points calculated successfully',
        ]);
    }

    /**
     * Find driver result in race results table
     */
    private function findDriverResult(array $results, string $driverId): ?array
    {
        foreach ($results as $result) {
            if ($result['driverId'] == $driverId) {
                return $result;
            }
        }
        return null;
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