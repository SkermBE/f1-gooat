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
        $player = Module::getCurrentPlayer();

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
            ->andWhere(['not', ['driverCode' => ['', null]]])
            ->all();

        $availableDrivers = [];
        $selectedDrivers = [];
        foreach ($drivers as $driver) {
            if (!in_array($driver->driverId, $selectedIds)) {
                $availableDrivers[] = $driver;
            } else {
                $selectedDrivers[] = $driver;
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

        // Check if player has already used booster this season
        $boosterAvailable = false;
        if ($player) {
            $existingBooster = Entry::find()
                ->section('predictions')
                ->siteId($race->siteId)
                ->relatedTo(['targetElement' => $player->id, 'field' => 'predictionPlayer'])
                ->boosterUsed(true)
                ->one();
            $boosterAvailable = !$existingBooster;
        }

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
            'selectedDrivers' => $selectedDrivers,
            'boosterAvailable' => $boosterAvailable,
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
     * Driver list/overview page
     */
    public function actionDriverList()
    {
        $drivers = Entry::find()
            ->section('drivers')
            ->isActive(true)
            ->andWhere(['not', ['driverCode' => ['', null]]])
            ->orderBy(['teamName' => SORT_ASC, 'driverLastName' => SORT_ASC])
            ->all();

        return $this->renderTemplate('f1/driver-list', [
            'drivers' => $drivers,
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
     * Driver detail/profile page
     */
    public function actionDriverProfile(int $driverId)
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $driver = Entry::find()->id($driverId)->section('drivers')->siteId($siteId)->one();
        if (!$driver) {
            throw new \yii\web\NotFoundHttpException('Driver not found');
        }

        // Get all completed races this season to extract driver stats from raceResults
        $races = Entry::find()
            ->section('races')
            ->siteId($siteId)
            ->orderBy(['raceRound' => SORT_DESC])
            ->all();

        $wins = 0;
        $podiums = 0;
        $p10Finishes = 0;
        $dnfs = 0;
        $totalRacesFinished = 0;
        $bestFinish = null;
        $positionSum = 0;
        $seasonResults = [];

        foreach ($races as $race) {
            $raceResult = null;

            if ($race->raceResults) {
                foreach ($race->raceResults as $result) {
                    if ($result['driverId'] === $driver->driverId) {
                        $raceResult = $result;
                        break;
                    }
                }
            }

            $entry = [
                'race' => $race,
                'result' => $raceResult,
                'hasResults' => $race->raceStatus == 'completed' && $race->raceResults && count($race->raceResults) > 1,
            ];
            $seasonResults[] = $entry;

            if (!$raceResult) {
                continue;
            }

            $position = (int)$raceResult['position'];
            $status = $raceResult['status'] ?? '';

            if ($status === 'Finished') {
                $totalRacesFinished++;
                $positionSum += $position;

                if ($position === 1) $wins++;
                if ($position <= 3) $podiums++;
                if ($position === 10) $p10Finishes++;

                if ($bestFinish === null || $position < $bestFinish) {
                    $bestFinish = $position;
                }
            } elseif ($status === 'DNF' || $status === 'DSQ') {
                $dnfs++;
            }
        }

        $avgPosition = $totalRacesFinished > 0 ? round($positionSum / $totalRacesFinished, 1) : null;

        // Get predictions for this driver (how many times picked, by whom)
        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->driverId($driver->driverId)
            ->all();

        $timesPicked = count($predictions);
        $totalPointsGenerated = 0;
        $finishedPicks = 0;
        $pickedBy = [];

        foreach ($predictions as $prediction) {
            $predRace = $prediction->predictionRace->one();
            $isCompleted = $predRace && $predRace->raceStatus == 'completed';

            if ($isCompleted) {
                $totalPointsGenerated += $prediction->pointsEarned ?? 0;
                $finishedPicks++;
            }

            $player = $prediction->predictionPlayer->one();
            if ($player) {
                $pid = $player->id;
                if (!isset($pickedBy[$pid])) {
                    $pickedBy[$pid] = ['player' => $player, 'count' => 0, 'points' => 0];
                }
                $pickedBy[$pid]['count']++;
                if ($isCompleted) {
                    $pickedBy[$pid]['points'] += $prediction->pointsEarned ?? 0;
                }
            }
        }

        $avgPointsGenerated = $finishedPicks > 0 ? round($totalPointsGenerated / $finishedPicks, 1) : null;

        // Sort by pick count descending
        usort($pickedBy, fn($a, $b) => $b['count'] <=> $a['count']);

        return $this->renderTemplate('f1/driver-profile', [
            'driver' => $driver,
            'wins' => $wins,
            'podiums' => $podiums,
            'p10Finishes' => $p10Finishes,
            'dnfs' => $dnfs,
            'bestFinish' => $bestFinish,
            'avgPosition' => $avgPosition,
            'totalRacesFinished' => $totalRacesFinished,
            'timesPicked' => $timesPicked,
            'totalPointsGenerated' => $totalPointsGenerated,
            'avgPointsGenerated' => $avgPointsGenerated,
            'pickedBy' => $pickedBy,
            'seasonResults' => $seasonResults,
        ]);
    }

    /**
     * Get current selector helper — last place picks first.
     * Uses calculated standings (reversed) so pick order matches the displayed leaderboard.
     */
    private function getCurrentSelector(int $raceId): ?Entry
    {
        $selectedCount = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();

        // Use calculated standings so pick order is consistent with displayed leaderboard
        $standings = Module::calculateSeasonStandings();

        if (!empty($standings)) {
            // Reverse: last place picks first
            $reversed = array_reverse($standings);
            if (isset($reversed[$selectedCount])) {
                return Entry::find()->id($reversed[$selectedCount]['id'])->one();
            }
            return null;
        }

        // Fallback: no completed races / no predictions yet — use stored fields
        // Add title as final tiebreaker for deterministic ordering
        $players = Entry::find()
            ->section('players')
            ->orderBy('totalPoints asc, currentStanding desc, title desc')
            ->all();

        if (isset($players[$selectedCount])) {
            return $players[$selectedCount];
        }

        return null;
    }
}
