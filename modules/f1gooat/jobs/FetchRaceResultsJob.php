<?php

namespace modules\f1gooat\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use GuzzleHttp\Client;
use modules\f1gooat\Module;
use modules\f1gooat\RaceResultsService;

class FetchRaceResultsJob extends BaseJob
{
    public int $raceId;

    public function execute($queue): void
    {
        $race = Entry::find()->id($this->raceId)->siteId('*')->one();

        if (!$race) {
            throw new \Exception('Race not found');
        }

        $this->setProgress($queue, 0.2, 'Fetching results from Jolpica API');

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

            $formattedResults = RaceResultsService::formatResults($results);

            $this->setProgress($queue, 0.7, 'Saving results and calculating points');

            if (!RaceResultsService::processRaceResults($race, $formattedResults)) {
                throw new \Exception('Could not save race results: ' . json_encode($race->getErrors()));
            }

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
}
