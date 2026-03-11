<?php

namespace modules\f1gooat;

use Craft;
use yii\base\Event;
use yii\base\Module as BaseModule;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\View;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use modules\f1gooat\PointsCalculator;
use modules\f1gooat\CacheService;

/**
 * F1 Prediction Game Module
 */
class Module extends BaseModule
{
    public static $instance;

    public function __construct($id, $parent = null, $config = [])
    {
        Craft::setAlias('@modules/f1gooat', __DIR__);

        // Must be set before parent::__construct() — Yii2 resolves controllers early
        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'modules\\f1gooat\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\f1gooat\\controllers';
        }

        parent::__construct($id, $parent, $config);
    }

    public function init()
    {
        parent::init();
        self::$instance = $this;

        // Register URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // API routes
                $event->rules['prediction/submit'] = 'f1-gooat/prediction/submit-prediction';
                $event->rules['prediction/available-drivers'] = 'f1-gooat/prediction/get-available-drivers';
                $event->rules['prediction/selection-status'] = 'f1-gooat/prediction/get-selection-status';
                $event->rules['race/fetch-results/<raceId:\d+>'] = 'f1-gooat/race/fetch-race-results';
                $event->rules['race/calculate-points/<raceId:\d+>'] = 'f1-gooat/race/calculate-points';
                $event->rules['leaderboard/standings'] = 'f1-gooat/leaderboard/get-standings';
                $event->rules['leaderboard/race-breakdown/<raceId:\d+>'] = 'f1-gooat/leaderboard/get-race-breakdown';
                $event->rules['leaderboard/season-chart'] = 'f1-gooat/leaderboard/get-season-chart';
                $event->rules['prediction/skip'] = 'f1-gooat/prediction/skip-player';

                // Update actions (web-accessible import/sync)
                $event->rules['update/sync-drivers'] = 'f1-gooat/update/sync-drivers';
                $event->rules['update/sync-races'] = 'f1-gooat/update/sync-races';
                $event->rules['update/fetch-results'] = 'f1-gooat/update/fetch-all-results';

                // Auth
                $event->rules['player-login'] = 'f1-gooat/auth/login';
                $event->rules['player-logout'] = 'f1-gooat/auth/logout';

                // Frontend routes (season is now determined by site, no <season> param needed)
                $event->rules['select/<raceId:\d+>'] = 'f1-gooat/frontend/select-driver';
                $event->rules['standings'] = 'f1-gooat/frontend/standings';
                $event->rules['results/<raceId:\d+>'] = 'f1-gooat/frontend/race-results';
                $event->rules['races'] = 'f1-gooat/frontend/race-list';
                $event->rules['player/<playerId:\d+>'] = 'f1-gooat/frontend/player-profile';
                $event->rules['drivers'] = 'f1-gooat/frontend/driver-list';
                $event->rules['driver/<driverId:\d+>'] = 'f1-gooat/frontend/driver-profile';
            }
        );

        // Inject globals into all site templates
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE,
                function (\craft\events\TemplateEvent $event) {
                    if (!Craft::$app->getRequest()->getIsCpRequest()) {
                        $event->variables['currentPlayer'] = self::getCurrentPlayer();
                        $event->variables['availableSites'] = self::getAvailableSites();
                        $event->variables['currentSeasonYear'] = self::getCurrentSeasonYear();
                        $event->variables['currentSite'] = Craft::$app->getSites()->getCurrentSite();
                        $event->variables['pointsMap'] = PointsCalculator::POINTS_MAP;
                    }
                }
            );
        }

        Craft::info(
            'F1 Prediction Game module loaded',
            __METHOD__
        );
    }

    /**
     * Get the currently logged-in player from the session
     */
    public static function getCurrentPlayer(): ?Entry
    {
        $playerEmail = Craft::$app->getSession()->get('playerEmail');
        if (!$playerEmail) {
            return null;
        }
        return Entry::find()
            ->section('players')
            ->playerEmail($playerEmail)
            ->one();
    }

    /**
     * Get all available season sites (from the same site group), newest first
     */
    public static function getAvailableSites(): array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteGroup = $currentSite->getGroup();
        $sites = Craft::$app->getSites()->getSitesByGroupId($siteGroup->id);

        // Sort by handle descending (season2026 > season2025)
        usort($sites, fn($a, $b) => strcmp($b->handle, $a->handle));

        return $sites;
    }

    /**
     * Get the season year from the current site handle (e.g. "season2026" → 2026)
     */
    public static function getCurrentSeasonYear(): int
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        return (int) str_replace('season', '', $site->handle);
    }

    /**
     * Calculate standings for the current site from predictions.
     * Craft auto-scopes queries to the current site in web requests.
     * For non-web contexts (queue jobs, console), pass $siteId explicitly.
     */
    public static function calculateSeasonStandings(?int $siteId = null): array
    {
        $cacheKey = CacheService::siteKey('standings', $siteId);

        return CacheService::getOrSet(
            $cacheKey,
            [CacheService::TAG_STANDINGS, CacheService::TAG_PREDICTIONS, CacheService::TAG_PLAYERS],
            CacheService::DURATION_MEDIUM,
            function () use ($siteId) {
                return self::computeSeasonStandings($siteId);
            }
        );
    }

    /**
     * Internal: compute standings from the database (uncached).
     */
    private static function computeSeasonStandings(?int $siteId): array
    {
        $racesQuery = Entry::find()->section('races');
        $predictionsQuery = Entry::find()->section('predictions');

        if ($siteId) {
            $racesQuery->siteId($siteId);
            $predictionsQuery->siteId($siteId);
        }

        $races = $racesQuery->orderBy(['raceRound' => SORT_ASC])->all();
        $raceIds = array_map(fn($r) => $r->id, $races);

        if (empty($raceIds)) {
            return [];
        }

        $predictions = $predictionsQuery
            ->relatedTo(['targetElement' => $raceIds, 'field' => 'predictionRace'])
            ->all();

        // Find the latest completed race to exclude for previous standings
        $completedRaces = array_filter($races, fn($r) => $r->raceStatus == 'completed');
        $latestCompletedRace = !empty($completedRaces) ? end($completedRaces) : null;
        $latestRaceId = $latestCompletedRace ? $latestCompletedRace->id : null;

        // Sum points per player (current + previous)
        $playerPoints = [];
        $playerPrevPoints = [];
        $playerEntries = [];

        foreach ($predictions as $prediction) {
            $player = $prediction->predictionPlayer->one();
            if (!$player) continue;

            $pid = $player->id;
            $points = $prediction->pointsEarned ?? 0;
            $predRace = $prediction->predictionRace->one();
            $predRaceId = $predRace ? $predRace->id : null;

            if (!isset($playerPoints[$pid])) {
                $playerPoints[$pid] = 0;
                $playerPrevPoints[$pid] = 0;
                $playerEntries[$pid] = $player;
            }

            $playerPoints[$pid] += $points;

            // Previous = all points except from the latest completed race
            if ($predRaceId !== $latestRaceId) {
                $playerPrevPoints[$pid] += $points;
            }
        }

        // Sort by points desc, then by currentStanding asc as tiebreaker
        $sortable = [];
        foreach ($playerPoints as $pid => $points) {
            $sortable[$pid] = [
                'points' => $points,
                'standing' => $playerEntries[$pid]->currentStanding ?? PHP_INT_MAX,
            ];
        }
        uasort($sortable, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points']; // points descending
            }
            return $a['standing'] <=> $b['standing']; // standing ascending
        });
        $playerPoints = array_map(fn($s) => $s['points'], $sortable);

        // Build previous position map
        arsort($playerPrevPoints);
        $prevPositions = [];
        $pos = 1;
        foreach ($playerPrevPoints as $pid => $pts) {
            $prevPositions[$pid] = $pos;
            $pos++;
        }

        // Build standings array
        $standings = [];
        $position = 1;
        foreach ($playerPoints as $pid => $points) {
            $player = $playerEntries[$pid];
            $prevPos = $prevPositions[$pid] ?? $position;
            $posChange = $prevPos - $position; // positive = moved up

            $standings[] = [
                'id' => $pid,
                'title' => $player->title,
                'totalPoints' => $points,
                'currentStanding' => $position,
                'previousStanding' => $prevPos,
                'positionChange' => $posChange,
                'player' => $player,
            ];
            $position++;
        }

        return $standings;
    }

    /**
     * Get the primary (current) season site for redirects
     */
    public static function getPrimarySeasonSite(): ?\craft\models\Site
    {
        return Craft::$app->getSites()->getPrimarySite();
    }

    /**
     * Get the Jolpica F1 API base URL from environment.
     */
    public static function getApiBaseUrl(): string
    {
        return getenv('JOLPICA_API_URL') ?: 'https://api.jolpi.ca/ergast/f1';
    }
}
