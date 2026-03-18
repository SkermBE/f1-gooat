<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\RaceStatus;
use modules\f1gooat\RaceResultsService;
use modules\f1gooat\CacheService;

class RaceController extends Controller
{
    protected array|int|bool $allowAnonymous = ['fetch-race-results', 'calculate-points'];

    /**
     * Fetch race results from Jolpica API and calculate points instantly
     */
    public function actionFetchRaceResults(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->siteId('*')->one();

        if (!$race) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race not found',
            ]);
        }

        if ($race->raceStatus != RaceStatus::SELECTION_CLOSED && $race->raceStatus != RaceStatus::COMPLETED) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race is not ready for results fetching',
            ]);
        }

        try {
            $client = new Client();
            $apiBase = Module::getApiBaseUrl();
            $url = "{$apiBase}/{$race->season}/{$race->raceRound}/results/";

            $response = $client->get($url);
            $data = json_decode($response->getBody(), true);

            $results = $data['MRData']['RaceTable']['Races'][0]['Results'] ?? [];

            if (empty($results)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No results found in API response',
                ]);
            }

            $formattedResults = RaceResultsService::formatResults($results);

            if (!RaceResultsService::processRaceResults($race, $formattedResults)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Could not save race results',
                ]);
            }

            return $this->asJson([
                'success' => true,
                'message' => 'Results fetched and points calculated',
            ]);

        } catch (\Exception $e) {
            Craft::error('Failed to fetch race results: ' . $e->getMessage(), __METHOD__);
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to fetch results: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Recalculate points from existing race results
     */
    public function actionCalculatePoints(int $raceId): Response
    {
        $race = Entry::find()->id($raceId)->siteId('*')->one();

        if (!$race || !$race->raceResults) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race results not available',
            ]);
        }

        RaceResultsService::calculatePointsForRace($race, $race->siteId);
        RaceResultsService::updatePlayerStandings($race->siteId);
        CacheService::invalidateAfterRaceResults();

        return $this->asJson([
            'success' => true,
            'message' => 'Points recalculated successfully',
        ]);
    }
}
