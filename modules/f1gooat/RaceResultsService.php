<?php

namespace modules\f1gooat;

use Craft;
use craft\elements\Entry;

/**
 * Consolidates race result processing logic previously duplicated across
 * RaceController, UpdateController, and FetchRaceResultsJob.
 */
class RaceResultsService
{
    /**
     * Format raw Jolpica API results into our internal format.
     */
    public static function formatResults(array $apiResults): array
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
     * Calculate and save points for all predictions of a given race.
     */
    public static function calculatePointsForRace(Entry $race, int $siteId): void
    {
        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $race->id, 'field' => 'predictionRace'])
            ->all();

        foreach ($predictions as $prediction) {
            $result = self::findDriverResult($race->raceResults, $prediction->driverCode);

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
     * Find a driver's result in the race results array.
     */
    public static function findDriverResult(array $results, string $driverCode): ?array
    {
        foreach ($results as $result) {
            if ($result['driverCode'] === $driverCode) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Update all player standings (totals + ranking) for a given site.
     */
    public static function updatePlayerStandings(int $siteId): void
    {
        $players = Entry::find()->section('players')->siteId($siteId)->all();

        // Store previous standings
        foreach ($players as $player) {
            $player->setFieldValue('previousStanding', $player->currentStanding ?? 0);
        }

        // Calculate season totals
        $seasonRaceIds = Entry::find()->section('races')->siteId($siteId)->ids();

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

        // Sort players by points descending
        usort($players, fn($a, $b) => ($b->totalPoints ?? 0) <=> ($a->totalPoints ?? 0));

        // Assign new standings
        foreach ($players as $index => $player) {
            $player->setFieldValue('currentStanding', $index + 1);
            Craft::$app->getElements()->saveElement($player);
        }
    }

    /**
     * Auto-open selection for the next race after completion.
     */
    public static function openNextRace(Entry $completedRace): void
    {
        $nextRace = Entry::find()
            ->section('races')
            ->siteId($completedRace->siteId)
            ->raceRound($completedRace->raceRound + 1)
            ->one();

        if ($nextRace && $nextRace->raceStatus == RaceStatus::UPCOMING) {
            $nextRace->setFieldValue('raceStatus', RaceStatus::SELECTION_OPEN);
            if (Craft::$app->getElements()->saveElement($nextRace)) {
                Craft::info("Auto-opened selection for Round {$nextRace->raceRound}: {$nextRace->title}", __METHOD__);
            }
        }
    }

    /**
     * Full pipeline: save results → calculate points → update standings → open next race → invalidate caches.
     * Used by both synchronous (controller) and async (queue job) contexts.
     */
    public static function processRaceResults(Entry $race, array $formattedResults): bool
    {
        $siteId = $race->siteId;

        $race->setFieldValue('raceResults', $formattedResults);
        $race->setFieldValue('raceStatus', RaceStatus::COMPLETED);

        if (!Craft::$app->getElements()->saveElement($race)) {
            return false;
        }

        self::calculatePointsForRace($race, $siteId);
        self::updatePlayerStandings($siteId);
        self::openNextRace($race);
        CacheService::invalidateAfterRaceResults();

        return true;
    }
}
