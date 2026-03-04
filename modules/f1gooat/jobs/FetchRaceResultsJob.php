<?php

namespace modules\f1gooat\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\PointsCalculator;

class FetchRaceResultsJob extends BaseJob
{
    public int $raceId;

    public function execute($queue): void
    {
        $race = Entry::find()->id($this->raceId)->siteId('*')->one();

        if (!$race) {
            throw new \Exception('Race not found');
        }

        $siteId = $race->siteId;

        $this->setProgress($queue, 0.2, 'Fetching results from Jolpica API');

        // Fetch from Jolpica API
        $client = new Client();
        $apiBase = Module::getApiBaseUrl();
        $url = "{$apiBase}/{$race->season}/{$race->raceRound}/results/";

        try {
            $response = $client->get($url);
            $data = json_decode($response->getBody(), true);

            $this->setProgress($queue, 0.5, 'Processing results');

            $results = $data['MRData']['RaceTable']['Races'][0]['Results'] ?? [];

            if (empty($results)) {
                throw new \Exception('No results found in API response');
            }

            // Format results
            $formattedResults = $this->formatResults($results);

            $this->setProgress($queue, 0.7, 'Saving results');

            // Save to race entry
            $race->setFieldValue('raceResults', $formattedResults);
            $race->setFieldValue('raceStatus', 'completed');

            if (!Craft::$app->getElements()->saveElement($race)) {
                throw new \Exception('Could not save race results: ' . json_encode($race->getErrors()));
            }

            $this->setProgress($queue, 0.9, 'Calculating points');

            // Trigger point calculation
            $this->calculatePoints($race, $siteId);

            // Auto-open selection for the next race
            $this->openNextRace($race, $siteId);

            $this->setProgress($queue, 1, 'Complete');

        } catch (\Exception $e) {
            Craft::error('Failed to fetch race results: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    protected function defaultDescription(): string
    {
        return 'Fetching race results from Jolpica API';
    }

    private function formatResults(array $apiResults): array
    {
        $formatted = [];

        foreach ($apiResults as $result) {
            $status = $result['status'] ?? '';

            // Determine if driver finished
            $isFinished = $status === 'Finished' ||
                         strpos($status, '+') === 0 ||
                         strpos($status, 'Lap') !== false;

            $formatted[] = [
                'position' => (int)$result['position'],
                'driverCode' => $result['Driver']['code'] ?? '',
                'driverId' => $result['Driver']['driverId'] ?? '',
                'status' => $isFinished ? 'Finished' : 'DNF',
            ];
        }

        return $formatted;
    }

    private function calculatePoints(Entry $race, int $siteId): void
    {
        $predictions = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $race->id, 'field' => 'predictionRace'])
            ->all();

        foreach ($predictions as $prediction) {
            $result = $this->findDriverResult($race->raceResults, $prediction->driverId);

            if (!$result || $result['status'] !== 'Finished') {
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

        // Update player standings
        $this->updatePlayerStandings($siteId);
    }

    private function findDriverResult(array $results, string $driverId): ?array
    {
        foreach ($results as $result) {
            if ($result['driverId'] === $driverId) {
                return $result;
            }
        }
        return null;
    }

    private function openNextRace(Entry $completedRace, int $siteId): void
    {
        $nextRace = Entry::find()
            ->section('races')
            ->siteId($siteId)
            ->raceRound($completedRace->raceRound + 1)
            ->one();

        if ($nextRace && $nextRace->raceStatus === 'upcoming') {
            $nextRace->setFieldValue('raceStatus', 'selection_open');
            if (Craft::$app->getElements()->saveElement($nextRace)) {
                Craft::info("Auto-opened selection for Round {$nextRace->raceRound}: {$nextRace->title}", __METHOD__);
            }
        }
    }

    private function updatePlayerStandings(int $siteId): void
    {
        $players = Entry::find()->section('players')->siteId($siteId)->all();

        foreach ($players as $player) {
            $player->setFieldValue('previousStanding', $player->currentStanding ?? 0);
        }

        foreach ($players as $player) {
            $predictions = Entry::find()
                ->section('predictions')
                ->siteId($siteId)
                ->relatedTo(['targetElement' => $player->id, 'field' => 'predictionPlayer'])
                ->all();

            $totalPoints = 0;
            foreach ($predictions as $prediction) {
                $totalPoints += $prediction->pointsEarned ?? 0;
            }

            $player->setFieldValue('totalPoints', $totalPoints);
        }

        usort($players, function($a, $b) {
            return ($b->totalPoints ?? 0) <=> ($a->totalPoints ?? 0);
        });

        foreach ($players as $index => $player) {
            $player->setFieldValue('currentStanding', $index + 1);
            Craft::$app->getElements()->saveElement($player);
        }
    }
}
