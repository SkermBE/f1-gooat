<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use modules\f1gooat\Module;
use modules\f1gooat\CacheService;

class LeaderboardController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Get season standings (scoped to current site/season)
     */
    public function actionGetStandings(): Response
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $cacheKey = CacheService::siteKey('leaderboard.standings', $siteId);

        $result = CacheService::getOrSet(
            $cacheKey,
            [CacheService::TAG_STANDINGS, CacheService::TAG_PREDICTIONS, CacheService::TAG_PLAYERS],
            CacheService::DURATION_MEDIUM,
            function () use ($siteId) {
                $standings = Module::calculateSeasonStandings();
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

                return $result;
            }
        );

        $response = $this->asJson([
            'success' => true,
            'season' => Module::getCurrentSeasonYear(),
            'standings' => $result,
        ]);

        // Allow browser caching for 5 minutes, stale-while-revalidate for 1 hour
        $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=3600');

        return $response;
    }

    /**
     * Get detailed race breakdown for a specific race
     */
    public function actionGetRaceBreakdown(int $raceId): Response
    {
        $cacheKey = CacheService::siteKey("raceBreakdown.{$raceId}");

        $data = CacheService::getOrSet(
            $cacheKey,
            [CacheService::TAG_PREDICTIONS, CacheService::TAG_RACES],
            CacheService::DURATION_LONG,
            function () use ($raceId) {
                $race = Entry::find()->id($raceId)->one();

                if (!$race) {
                    return null;
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

                return [
                    'race' => [
                        'id' => $race->id,
                        'name' => $race->title,
                        'round' => $race->raceRound,
                        'date' => $race->raceDate,
                    ],
                    'actualP10' => $actualP10,
                    'perfectPredictions' => $perfectPredictions,
                    'breakdown' => $breakdown,
                ];
            }
        );

        if ($data === null) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race not found',
            ]);
        }

        $response = $this->asJson(array_merge(['success' => true], $data));

        // Completed race breakdowns are immutable — cache aggressively
        $response->headers->set('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');

        return $response;
    }

    /**
     * Get cumulative points per player per completed race (for season chart)
     */
    public function actionGetSeasonChart(): Response
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $cacheKey = CacheService::siteKey('seasonChart', $siteId);

        $data = CacheService::getOrSet(
            $cacheKey,
            [CacheService::TAG_SEASON_CHART, CacheService::TAG_PREDICTIONS, CacheService::TAG_PLAYERS],
            CacheService::DURATION_LONG,
            function () use ($siteId) {
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

                $labels = [];
                foreach ($races as $race) {
                    $labels[] = $race->title;
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

                return [
                    'labels' => $labels,
                    'datasets' => $datasets,
                ];
            }
        );

        $response = $this->asJson(array_merge(['success' => true], $data));

        // Season chart only changes after race completion — cache aggressively
        $response->headers->set('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');

        return $response;
    }

    /**
     * Get player-specific statistics
     */
    public function actionGetPlayerStats(int $playerId): Response
    {
        $cacheKey = CacheService::siteKey("playerStats.{$playerId}");

        $data = CacheService::getOrSet(
            $cacheKey,
            [CacheService::TAG_PREDICTIONS, CacheService::TAG_PLAYERS],
            CacheService::DURATION_MEDIUM,
            function () use ($playerId) {
                $player = Entry::find()->id($playerId)->one();

                if (!$player) {
                    return null;
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

                    $driverCode = $prediction->driverCode;
                    if (!isset($driverPreferences[$driverCode])) {
                        $driverPreferences[$driverCode] = 0;
                    }
                    $driverPreferences[$driverCode]++;
                }

                arsort($driverPreferences);

                return [
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
                ];
            }
        );

        if ($data === null) {
            return $this->asJson([
                'success' => false,
                'error' => 'Player not found',
            ]);
        }

        $response = $this->asJson(array_merge(['success' => true], $data));
        $response->headers->set('Cache-Control', 'public, max-age=300, stale-while-revalidate=3600');

        return $response;
    }
}
