<?php

namespace modules\f1gooat;

use Craft;
use craft\elements\Entry;

/**
 * Handles selection order logic — previously duplicated in
 * FrontendController::getCurrentSelector() and PredictionController::getCurrentSelector().
 */
class SelectionService
{
    /**
     * Get the player whose turn it is to select for a given race.
     * Last place in standings picks first (reversed standings order).
     */
    public static function getCurrentSelector(int $raceId, ?int $siteId = null): ?Entry
    {
        $selectedCount = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();

        // Get all players for this site
        $playersQuery = Entry::find()->section('players');
        if ($siteId) {
            $playersQuery->siteId($siteId);
        }
        $allPlayers = $playersQuery->indexBy('id')->all();

        // Use calculated standings so pick order is consistent with displayed leaderboard
        $standings = Module::calculateSeasonStandings($siteId);

        if (!empty($standings)) {
            // Start with standings order (last place picks first)
            $pickOrder = array_reverse($standings);

            // Append any players not yet in standings (no predictions yet)
            $standingIds = array_column($standings, 'id');
            foreach ($allPlayers as $pid => $player) {
                if (!in_array($pid, $standingIds)) {
                    $pickOrder[] = ['id' => $pid, 'player' => $player];
                }
            }

            if (isset($pickOrder[$selectedCount])) {
                return Entry::find()->id($pickOrder[$selectedCount]['id'])->one();
            }
            return null;
        }

        // Fallback: no standings at all — use stored fields
        $players = array_values($allPlayers);
        usort($players, function ($a, $b) {
            $pointsA = $a->totalPoints ?? 0;
            $pointsB = $b->totalPoints ?? 0;
            if ($pointsA !== $pointsB) return $pointsA <=> $pointsB;
            $standingA = $a->currentStanding ?? PHP_INT_MAX;
            $standingB = $b->currentStanding ?? PHP_INT_MAX;
            if ($standingA !== $standingB) return $standingB <=> $standingA;
            return strcmp($b->title, $a->title);
        });

        return $players[$selectedCount] ?? null;
    }

    /**
     * Get the count of predictions already submitted for a race.
     */
    public static function getSelectedCount(int $raceId): int
    {
        return Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();
    }

    /**
     * Check if a player has already used their booster this season.
     */
    public static function hasUsedBooster(Entry $player, int $siteId): bool
    {
        return (bool)Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $player->id, 'field' => 'predictionPlayer'])
            ->boosterUsed(true)
            ->one();
    }
}
