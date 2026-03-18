<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use yii\web\Response;
use modules\f1gooat\Module;
use modules\f1gooat\RaceStatus;
use modules\f1gooat\CacheService;
use modules\f1gooat\SelectionService;

class PredictionController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Submit a prediction
     */
    public function actionSubmitPrediction(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $raceId = $request->getBodyParam('raceId');
        $driverId = $request->getBodyParam('driverId');

        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $isAdmin = Craft::$app->getUser()->getIdentity() && Craft::$app->getUser()->getIdentity()->admin;

        $player = Module::getCurrentPlayer();
        if (!$player && !$isAdmin) {
            return $this->asJson([
                'success' => false,
                'error' => 'Not logged in',
            ]);
        }

        // Get race — must be on current site and open for selection
        $race = Entry::find()->id($raceId)->siteId($siteId)->one();

        if (!$race || $race->raceStatus != RaceStatus::SELECTION_OPEN) {
            return $this->asJson([
                'success' => false,
                'error' => 'Selection is not open for this race',
            ]);
        }

        // Validate driver exists on this site
        $driver = Entry::find()
            ->section('drivers')
            ->siteId($siteId)
            ->driverId($driverId)
            ->one();

        if (!$driver) {
            return $this->asJson([
                'success' => false,
                'error' => 'Driver not found',
            ]);
        }

        if (!$driver->isActive) {
            return $this->asJson([
                'success' => false,
                'error' => 'This driver is unavailable',
            ]);
        }

        // Check if it's this player's turn (admins vote on behalf of currentSelector)
        $currentSelector = SelectionService::getCurrentSelector($raceId, $siteId);

        if ($isAdmin) {
            if (!$currentSelector) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No player to vote for — all selections complete',
                ]);
            }
            $player = $currentSelector;
        } elseif (!$currentSelector || $currentSelector->id != $player->id) {
            return $this->asJson([
                'success' => false,
                'error' => 'It is not your turn to select',
            ]);
        }

        // Check if driver already selected for this race
        $existingPrediction = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->driverId($driverId)
            ->one();

        if ($existingPrediction) {
            return $this->asJson([
                'success' => false,
                'error' => 'This driver has already been selected',
            ]);
        }

        // Get selection order
        $selectionOrder = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count() + 1;

        // Use validated driver data (not user input)
        $driverCode = $driver->driverCode;
        $driverName = trim($driver->driverFirstName . ' ' . $driver->driverLastName);

        // Check booster usage
        $boosterUsed = (bool)$request->getBodyParam('boosterUsed');

        if ($boosterUsed && SelectionService::hasUsedBooster($player, $siteId)) {
            return $this->asJson([
                'success' => false,
                'error' => 'You have already used your booster this season',
            ]);
        }

        // Create prediction on current site
        $prediction = new Entry();
        $prediction->sectionId = Craft::$app->getEntries()->getSectionByHandle('predictions')->id;
        $prediction->typeId = Craft::$app->getEntries()->getSectionByHandle('predictions')->getEntryTypes()[0]->id;
        $prediction->siteId = $siteId;

        $prediction->setFieldValues([
            'predictionRace' => [$raceId],
            'predictionPlayer' => [$player->id],
            'driverId' => $driverId,
            'driverCode' => $driverCode,
            'driverName' => $driverName,
            'selectionOrder' => $selectionOrder,
            'boosterUsed' => $boosterUsed,
        ]);

        if (!Craft::$app->getElements()->saveElement($prediction)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not save prediction',
            ]);
        }

        // Auto-close selection if all players have picked
        $totalPlayers = Entry::find()->section('players')->siteId($siteId)->count();
        if ($selectionOrder >= $totalPlayers) {
            $race->setFieldValue('raceStatus', RaceStatus::SELECTION_CLOSED);
            Craft::$app->getElements()->saveElement($race);
        }

        // Invalidate caches affected by the new prediction
        CacheService::invalidateAfterPrediction();

        return $this->asJson([
            'success' => true,
            'prediction' => [
                'id' => $prediction->id,
                'driverCode' => $driverCode,
                'driverName' => $driverName,
                'selectionOrder' => $selectionOrder,
                'totalPlayers' => $totalPlayers,
                'boosterUsed' => $boosterUsed,
            ],
        ]);
    }

    /**
     * Get available drivers for a race
     */
    public function actionGetAvailableDrivers(): Response
    {
        $raceId = Craft::$app->getRequest()->getQueryParam('raceId');

        // Get already selected driver IDs for this race
        $selectedPredictions = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->all();

        $selectedIds = [];
        foreach ($selectedPredictions as $prediction) {
            $selectedIds[] = $prediction->driverId;
        }

        // Get all active drivers not yet selected
        $drivers = Entry::find()
            ->section('drivers')
            ->isActive(true)
            ->all();

        $availableDrivers = [];
        foreach ($drivers as $driver) {
            if (!in_array($driver->driverId, $selectedIds)) {
                $photo = $driver->driverPhoto->one();
                $availableDrivers[] = [
                    'id' => $driver->id,
                    'driverId' => $driver->driverId,
                    'driverCode' => $driver->driverCode,
                    'firstName' => $driver->driverFirstName,
                    'lastName' => $driver->driverLastName,
                    'teamName' => $driver->teamName,
                    'photo' => $photo ? $photo->url : null,
                ];
            }
        }

        return $this->asJson([
            'success' => true,
            'drivers' => $availableDrivers,
        ]);
    }

    /**
     * Get current selection status
     */
    public function actionGetSelectionStatus(): Response
    {
        $raceId = Craft::$app->getRequest()->getQueryParam('raceId');

        $race = Entry::find()->id($raceId)->one();

        if (!$race) {
            return $this->asJson([
                'success' => false,
                'error' => 'Race not found',
            ]);
        }

        $totalPlayers = Entry::find()->section('players')->siteId($race->siteId)->count();
        $selectedCount = Entry::find()
            ->section('predictions')
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count();

        $currentSelector = SelectionService::getCurrentSelector($raceId, $race->siteId);

        $player = Module::getCurrentPlayer();
        $isPlayerTurn = $currentSelector && $player && $currentSelector->id == $player->id;

        return $this->asJson([
            'success' => true,
            'status' => $race->raceStatus,
            'totalPlayers' => $totalPlayers,
            'selectedCount' => $selectedCount,
            'currentSelector' => $currentSelector ? [
                'id' => $currentSelector->id,
                'name' => $currentSelector->title,
            ] : null,
            'isPlayerTurn' => $isPlayerTurn,
        ]);
    }

    /**
     * Skip the current player's turn — any logged-in player or admin can trigger this
     */
    public function actionSkipPlayer(): Response
    {
        $this->requirePostRequest();

        $isAdmin = Craft::$app->getUser()->getIdentity() && Craft::$app->getUser()->getIdentity()->admin;
        $player = Module::getCurrentPlayer();
        if (!$player && !$isAdmin) {
            return $this->asJson([
                'success' => false,
                'error' => 'Not logged in',
            ]);
        }

        $request = Craft::$app->getRequest();
        $raceId = $request->getBodyParam('raceId');
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        $race = Entry::find()->id($raceId)->siteId($siteId)->one();

        if (!$race || $race->raceStatus != RaceStatus::SELECTION_OPEN) {
            return $this->asJson([
                'success' => false,
                'error' => 'Selection is not open for this race',
            ]);
        }

        $currentSelector = SelectionService::getCurrentSelector($raceId, $siteId);
        if (!$currentSelector) {
            return $this->asJson([
                'success' => false,
                'error' => 'No player to skip',
            ]);
        }

        // Get selection order
        $selectionOrder = Entry::find()
            ->section('predictions')
            ->siteId($siteId)
            ->relatedTo(['targetElement' => $raceId, 'field' => 'predictionRace'])
            ->count() + 1;

        // Create a skip prediction (no driver, 0 points)
        $prediction = new Entry();
        $prediction->sectionId = Craft::$app->getEntries()->getSectionByHandle('predictions')->id;
        $prediction->typeId = Craft::$app->getEntries()->getSectionByHandle('predictions')->getEntryTypes()[0]->id;
        $prediction->siteId = $siteId;

        $prediction->setFieldValues([
            'predictionRace' => [$raceId],
            'predictionPlayer' => [$currentSelector->id],
            'driverId' => 'SKIP',
            'driverCode' => 'SKIP',
            'driverName' => 'Skipped',
            'selectionOrder' => $selectionOrder,
            'pointsEarned' => 0,
        ]);

        if (!Craft::$app->getElements()->saveElement($prediction)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not save skip prediction',
            ]);
        }

        // Auto-close selection if all players have picked
        $totalPlayers = Entry::find()->section('players')->siteId($siteId)->count();
        if ($selectionOrder >= $totalPlayers) {
            $race->setFieldValue('raceStatus', RaceStatus::SELECTION_CLOSED);
            Craft::$app->getElements()->saveElement($race);
        }

        // Invalidate caches affected by the skip
        CacheService::invalidateAfterPrediction();

        return $this->asJson([
            'success' => true,
            'skippedPlayer' => $currentSelector->title,
            'selectionOrder' => $selectionOrder,
            'totalPlayers' => $totalPlayers,
        ]);
    }

}
