<?php

namespace modules\f1gooat\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\Queue;
use yii\console\ExitCode;
use modules\f1gooat\jobs\FetchRaceResultsJob;

class CronController extends Controller
{
    /**
     * Check all sites for races with 'selection_closed' status and queue result fetching.
     * Intended to run via cron every hour on Sundays.
     *
     * Usage: php craft f1-gooat/cron/fetch-results
     */
    public function actionFetchResults(): int
    {
        echo "Checking for races ready for results...\n";

        $sites = Craft::$app->getSites()->getAllSites();
        $totalQueued = 0;

        foreach ($sites as $site) {
            $races = Entry::find()
                ->section('races')
                ->siteId($site->id)
                ->raceStatus('selection_closed')
                ->all();

            foreach ($races as $race) {
                Queue::push(new FetchRaceResultsJob([
                    'raceId' => $race->id,
                ]));
                $totalQueued++;
                echo "[{$site->handle}] Queued: {$race->title} (Round {$race->raceRound})\n";
            }
        }

        if ($totalQueued === 0) {
            echo "No races ready for results.\n";
        } else {
            echo "\nQueued {$totalQueued} race(s) for result fetching.\n";
            echo "Running queue...\n";
            Craft::$app->getQueue()->run();
            echo "Queue complete.\n";
        }

        return ExitCode::OK;
    }
}
