<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use modules\f1gooat\Module;

class FrontendController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Driver selection page
     */
    public function actionSelectDriver(int $raceId)
    {
        // Require player session
        $player = Module::getCurrentPlayer();
        if (!$player) {
            Craft::$app->getSession()->set('returnUrl', Craft::$app->getRequest()->getUrl());
            return $this->redirect(UrlHelper::siteUrl('login'));
        }

        $race = Entry::find()->id($raceId)->one();

        if (!$race) {
            throw new \yii\web\NotFoundHttpException('Race not found');
        }

        // Get selection status (scoped to this site)
        $totalPlayers = Entry::find()->section('players')->siteId($race->siteId)->count();
        $selectedCount = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();

        $currentSelector = $this->getCurrentSelector($raceId);
        $isPlayerTurn = $currentSelector && $player && $currentSelector->id == $player->id;

        // Get available drivers
        $selectedPredictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->all();

        $selectedIds = [];
        foreach ($selectedPredictions as $prediction) {
            $selectedIds[] = $prediction->driverId;
        }

        $drivers = Entry::find()
            ->section('drivers')
            ->isActive(true)
            ->all();

        $availableDrivers = [];
        foreach ($drivers as $driver) {
            if (!in_array($driver->driverId, $selectedIds)) {
                $availableDrivers[] = $driver;
            }
        }

        // Get all selections (ordered by selection order)
        $allSelections = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->orderBy(['selectionOrder' => SORT_ASC])
            ->all();

        // Recent selections (last 3, reverse order for display)
        $recentSelections = array_slice(array_reverse($allSelections), 0, 3);

        return $this->renderTemplate('f1/select-driver', [
            'race' => $race,
            'player' => $player,
            'isPlayerTurn' => $isPlayerTurn,
            'currentSelector' => $currentSelector,
            'totalPlayers' => $totalPlayers,
            'selectedCount' => $selectedCount,
            'availableDrivers' => $availableDrivers,
            'recentSelections' => $recentSelections,
            'allSelections' => $allSelections,
            'selectionComplete' => $selectedCount >= $totalPlayers,
        ]);
    }

    /**
     * Standings/leaderboard page
     */
    public function actionStandings()
    {
        $standings = Module::calculateSeasonStandings();
        $currentPlayer = Module::getCurrentPlayer();

        return $this->renderTemplate('f1/standings', [
            'standings' => $standings,
            'currentPlayer' => $currentPlayer,
        ]);
    }

    /**
     * Race results page
     */
    public function actionRaceResults(int $raceId)
    {
        $race = Entry::find()->id($raceId)->one();

        if (!$race) {
            throw new \yii\web\NotFoundHttpException('Race not found');
        }

        $predictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->orderBy(['pointsEarned' => SORT_DESC])
            ->all();

        // Build full race classification with driver details
        $classification = [];
        $actualP10 = null;

        if ($race->raceResults) {
            // Preload all drivers for this site in one query
            $allDrivers = Entry::find()
                ->section('drivers')
                ->siteId($race->siteId)
                ->indexBy('driverId')
                ->all();

            foreach ($race->raceResults as $result) {
                $driverId = $result['driverId'] ?? '';
                $driver = $allDrivers[$driverId] ?? null;

                $entry = [
                    'position' => (int)$result['position'],
                    'driverCode' => $result['driverCode'] ?? '',
                    'driverId' => $driverId,
                    'firstName' => $driver ? $driver->driverFirstName : '',
                    'lastName' => $driver ? $driver->driverLastName : '',
                    'team' => $driver ? $driver->teamName : '',
                    'status' => $result['status'] ?? 'Finished',
                    'isP10' => (int)$result['position'] === 10,
                ];

                $classification[] = $entry;

                if ($entry['isP10']) {
                    $actualP10 = [
                        'code' => $entry['driverCode'],
                        'name' => trim($entry['firstName'] . ' ' . $entry['lastName']) ?: 'Unknown',
                        'team' => $entry['team'],
                    ];
                }
            }

            // Sort by position
            usort($classification, fn($a, $b) => $a['position'] <=> $b['position']);
        }

        $perfectCount = 0;
        foreach ($predictions as $prediction) {
            if ($prediction->actualPosition == 10) {
                $perfectCount++;
            }
        }

        return $this->renderTemplate('f1/race-results', [
            'race' => $race,
            'predictions' => $predictions,
            'classification' => $classification,
            'actualP10' => $actualP10,
            'perfectCount' => $perfectCount,
        ]);
    }

    /**
     * Race list/schedule page
     */
    public function actionRaceList()
    {
        $races = Entry::find()
            ->section('races')
            ->orderBy(['raceRound' => SORT_ASC])
            ->all();

        return $this->renderTemplate('f1/race-list', [
            'races' => $races,
        ]);
    }

    /**
     * Player profile page
     */
    public function actionPlayerProfile(int $playerId)
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Find the player — first try current site, then cross-site and match by email
        $player = Entry::find()->id($playerId)->siteId($siteId)->one();

        if (!$player) {
            // Player ID is from another site — find by email on current site
            $otherPlayer = Entry::find()->id($playerId)->siteId('*')->one();
            if ($otherPlayer) {
                $player = Entry::find()
                    ->section('players')
                    ->siteId($siteId)
                    ->playerEmail($otherPlayer->playerEmail)
                    ->one();
            }
        }

        if (!$player) {
            throw new \yii\web\NotFoundHttpException('Player not found');
        }

        // Get all predictions for this player in the current season
        $seasonRaceIds = Entry::find()->section('races')->siteId($siteId)->ids();

        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo([
                'and',
                ['targetElement' => $player->id, 'field' => 'predictionPlayer'],
                ['targetElement' => $seasonRaceIds, 'field' => 'predictionRace'],
            ])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        // Build race history
        $raceHistory = [];
        $perfectCount = 0;
        $totalPoints = 0;
        $driverPicks = [];

        foreach ($predictions as $prediction) {
            $race = $prediction->predictionRace->one();
            $isPerfect = $prediction->actualPosition == 10;

            if ($isPerfect) {
                $perfectCount++;
            }

            $points = $prediction->pointsEarned ?? 0;
            $totalPoints += $points;

            $raceHistory[] = [
                'race' => $race,
                'prediction' => $prediction,
                'isPerfect' => $isPerfect,
            ];

            // Track driver preferences
            $code = $prediction->driverCode;
            if (!isset($driverPicks[$code])) {
                $driverPicks[$code] = 0;
            }
            $driverPicks[$code]++;
        }

        arsort($driverPicks);

        $raceCount = count($predictions);
        $avgPoints = $raceCount > 0 ? round($totalPoints / $raceCount, 1) : 0;

        // Find best race
        $bestRace = null;
        $bestPoints = 0;
        foreach ($predictions as $prediction) {
            if (($prediction->pointsEarned ?? 0) > $bestPoints) {
                $bestPoints = $prediction->pointsEarned;
                $bestRace = $prediction->predictionRace->one();
            }
        }

        return $this->renderTemplate('f1/player-profile', [
            'player' => $player,
            'raceHistory' => $raceHistory,
            'perfectCount' => $perfectCount,
            'raceCount' => $raceCount,
            'avgPoints' => $avgPoints,
            'driverPicks' => $driverPicks,
            'bestRace' => $bestRace,
            'bestPoints' => $bestPoints,
        ]);
    }

    /**
     * Get current selector helper
     */
    private function getCurrentSelector(int $raceId): ?Entry
    {
        $selectedCount = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();

        $players = Entry::find()
            ->section('players')
            ->orderBy(['totalPoints' => SORT_ASC])
            ->all();

        if (isset($players[$selectedCount])) {
            return $players[$selectedCount];
        }

        return null;
    }
}
