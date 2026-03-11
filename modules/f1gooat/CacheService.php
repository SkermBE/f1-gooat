<?php

namespace modules\f1gooat;

use Craft;
use yii\caching\TagDependency;

/**
 * Centralized cache service for the F1 prediction game.
 *
 * Since data only changes after race results or during the brief selection window,
 * we can aggressively cache most queries. Cache is invalidated via tags at the
 * exact mutation points (prediction submit, results fetch, driver/race sync).
 */
class CacheService
{
    // Cache tags — used for targeted invalidation
    public const TAG_STANDINGS = 'f1.standings';
    public const TAG_SEASON_CHART = 'f1.seasonChart';
    public const TAG_RACES = 'f1.races';
    public const TAG_DRIVERS = 'f1.drivers';
    public const TAG_PREDICTIONS = 'f1.predictions';
    public const TAG_PLAYERS = 'f1.players';

    // Default durations (seconds)
    public const DURATION_LONG = 86400;      // 24 hours — for completed/historical data
    public const DURATION_MEDIUM = 3600;     // 1 hour — for standings/charts
    public const DURATION_SHORT = 300;       // 5 minutes — for selection-active pages

    /**
     * Get or set a cached value with tag dependency.
     *
     * @param string $key Cache key
     * @param array $tags Tags for invalidation
     * @param int $duration TTL in seconds
     * @param callable $callback Function that returns the data to cache
     * @return mixed
     */
    public static function getOrSet(string $key, array $tags, int $duration, callable $callback): mixed
    {
        $cache = Craft::$app->getCache();
        $dependency = new TagDependency(['tags' => $tags]);

        return $cache->getOrSet($key, $callback, $duration, $dependency);
    }

    /**
     * Invalidate all caches matching the given tags.
     * Clears both Yii data cache (PHP-level) and Craft template caches ({% cache %} tags).
     */
    public static function invalidate(array $tags): void
    {
        // Invalidate Yii2 data cache (controller-level caching)
        TagDependency::invalidate(Craft::$app->getCache(), $tags);

        // Note: Craft's {% cache %} Twig tags auto-invalidate when elements
        // used inside the block are saved/deleted — no manual invalidation needed.
    }

    /**
     * Invalidate everything after race results are processed.
     * This is the nuclear option — clears all game-related caches.
     */
    public static function invalidateAfterRaceResults(): void
    {
        self::invalidate([
            self::TAG_STANDINGS,
            self::TAG_SEASON_CHART,
            self::TAG_PREDICTIONS,
            self::TAG_PLAYERS,
            self::TAG_RACES,
        ]);
    }

    /**
     * Invalidate caches affected by a new prediction being submitted.
     */
    public static function invalidateAfterPrediction(): void
    {
        self::invalidate([
            self::TAG_PREDICTIONS,
            self::TAG_RACES, // race status may change (selection_closed)
        ]);
    }

    /**
     * Invalidate caches after driver sync.
     */
    public static function invalidateAfterDriverSync(): void
    {
        self::invalidate([self::TAG_DRIVERS]);
    }

    /**
     * Invalidate caches after race schedule sync.
     */
    public static function invalidateAfterRaceSync(): void
    {
        self::invalidate([self::TAG_RACES]);
    }

    /**
     * Build a site-scoped cache key.
     */
    public static function siteKey(string $prefix, ?int $siteId = null): string
    {
        $siteId = $siteId ?? Craft::$app->getSites()->getCurrentSite()->id;
        return "f1.{$prefix}.site{$siteId}";
    }
}
