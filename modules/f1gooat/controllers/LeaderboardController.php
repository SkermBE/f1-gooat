<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use modules\f1gooat\Module;

class LeaderboardController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Get season standings (scoped to current site/season)
     */
    public function actionGetStandings(): Response
    {
        $standings = Module::calculateSeasonStandings();

        // Enrich each entry with last race info for this site/season
        $seasonRaces = Entry::find()->section('races')->ids();

        $result = [];
        foreach ($standings as $i => $entry) {
            $player = $entry['player'];

            $lastPrediction = Entry::find()
                ->section('predictions')
                ->relatedTo([
                    'and',
                    ['targetElement' => $player->id, 'field' => 'predictionPlayer'],
                    ['targetElement' => $seasonRaces, 'field' => 'predictionRace'],
                ])
                ->orderBy(['dateCreated' => SORT_DESC])
                ->one();

            $lastRace = null;
            if ($lastPrediction) {
                $race = $lastPrediction->predictionRace->one();
                $lastRace = [
                    'raceName' => $race ? $race->title : '',
                    'pointsEarned' => $lastPrediction->pointsEarned ?? 0,
                ];
            }

            $result[] = [
                'id' => $entry['id'],
                'name' => $entry['title'],
                'currentStanding' => $entry['currentStanding'],
                'previousStanding' => 0,
                'totalPoints' => $entry['totalPoints'],
                'positionChange' => 0,
                'lastRace' => $lastRace,
            ];
        }

        return $this->asJson([
            'success' => true,
            'season' => Module::getCurrentSeasonYear(),
            'standings' => $result,
        ]);
    }

    /**
     * Get detailed race breakdown for a specific race
     */
    public function actionGetRaceBreakdown(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->one();

        if (!$race) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race not found',
            ]);
        }

        $predictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->orderBy(['pointsEarned' => SORT_DESC])
            ->all();

        // Find actual P10 driver
        $actualP10 = null;
        if ($race->raceResults) {
            foreach ($race->raceResults as $result) {
                if ((int)$result['position'] == 10) {
                    $actualP10 = [
                        'driverCode' => $result['driverCode'],
                        'driverId' => $result['driverId'],
                    ];
                    break;
                }
            }
        }

        $breakdown = [];
        $perfectPredictions = 0;

        foreach ($predictions as $prediction) {
            $player = $prediction->predictionPlayer->one();
            
            $isPerfect = $prediction->actualPosition == 10;
            if ($isPerfect) {
                $perfectPredictions++;
            }

            $breakdown[] = [
                'playerId' => $player ? $player->id : null,
                'playerName' => $player ? $player->title : 'Unknown',
                'driverCode' => $prediction->driverCode,
                'driverName' => $prediction->driverName,
                'actualPosition' => $prediction->actualPosition,
                'difference' => $prediction->actualPosition ? abs($prediction->actualPosition - 10) : null,
                'pointsEarned' => $prediction->pointsEarned ?? 0,
                'isPerfect' => $isPerfect,
                'isDnf' => $prediction->actualPosition === null,
            ];
        }

        return $this->asJson([
            'success' => true,
            'race' => [
                'id' => $race->id,
                'name' => $race->title,
                'round' => $race->raceRound,
                'date' => $race->raceDate,
            ],
            'actualP10' => $actualP10,
            'perfectPredictions' => $perfectPredictions,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get cumulative points per player per completed race (for season chart)
     */
    public function actionGetSeasonChart(): Response
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $races = Entry::find()
            ->section('races')
            ->siteId($siteId)
            ->raceStatus('completed')
            ->orderBy(['raceRound' => SORT_ASC])
            ->all();

        $players = Entry::find()
            ->section('players')
            ->siteId($siteId)
            ->orderBy('totalPoints DESC')
            ->all();

        // Build labels (race names) and cumulative points per player
        $labels = [];
        $raceIds = [];
        foreach ($races as $race) {
            $labels[] = $race->title;
            $raceIds[] = $race->id;
        }

        $datasets = [];
        foreach ($players as $player) {
            $cumulative = 0;
            $dataPoints = [];

            foreach ($races as $race) {
                $prediction = Entry::find()
                    ->section('predictions')
                    ->siteId($siteId)
                    ->relatedTo([
                        'and',
                        ['targetElement' => $player->id, 'field' => 'predictionPlayer'],
                        ['targetElement' => $race->id, 'field' => 'predictionRace'],
                    ])
                    ->one();

                $cumulative += $prediction ? ($prediction->pointsEarned ?? 0) : 0;
                $dataPoints[] = $cumulative;
            }

            $datasets[] = [
                'label' => $player->title,
                'data' => $dataPoints,
            ];
        }

        return $this->asJson([
            'success' => true,
            'labels' => $labels,
            'datasets' => $datasets,
        ]);
    }

    /**
     * Get player-specific statistics
     */
    public function actionGetPlayerStats(int $playerId): Response
    {
        $player = Entry::find()->id($playerId)->one();

        if (!$player) {
            return $this->asJson([
                'success' => false,
                'error' => 'Player not found',
            ]);
        }

        $predictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $playerId, 'field' => 'predictionPlayer'])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->all();

        $perfectPredictions = 0;
        $totalRaces = count($predictions);
        $raceHistory = [];
        $driverPreferences = [];

        foreach ($predictions as $prediction) {
            $race = $prediction->predictionRace->one();
            
            if ($prediction->actualPosition == 10) {
                $perfectPredictions++;
            }

            $raceHistory[] = [
                'raceId' => $race ? $race->id : null,
                'raceName' => $race ? $race->title : 'Unknown',
                'driverCode' => $prediction->driverCode,
                'driverName' => $prediction->driverName,
                'actualPosition' => $prediction->actualPosition,
                'pointsEarned' => $prediction->pointsEarned ?? 0,
            ];

            // Count driver preferences
            $driverCode = $prediction->driverCode;
            if (!isset($driverPreferences[$driverCode])) {
                $driverPreferences[$driverCode] = 0;
            }
            $driverPreferences[$driverCode]++;
        }

        arsort($driverPreferences);

        return $this->asJson([
            'success' => true,
            'player' => [
                'id' => $player->id,
                'name' => $player->title,
                'currentStanding' => $player->currentStanding ?? 0,
                'totalPoints' => $player->totalPoints ?? 0,
            ],
            'stats' => [
                'perfectPredictions' => $perfectPredictions,
                'totalRaces' => $totalRaces,
                'averagePoints' => $totalRaces > 0 ? round(($player->totalPoints ?? 0) / $totalRaces, 2) : 0,
            ],
            'raceHistory' => $raceHistory,
            'driverPreferences' => $driverPreferences,
        ]);
    }
}