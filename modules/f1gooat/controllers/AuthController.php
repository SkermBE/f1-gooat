<?php

namespace modules\f1gooat\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use yii\web\Response;

class AuthController extends Controller
{
    protected array|int|bool $allowAnonymous = ['login', 'logout'];

    /**
     * Show login form (GET) or process email login (POST)
     */
    public function actionLogin(): Response|string
    {
        // Already logged in — go home
        $playerEmail = Craft::$app->getSession()->get('playerEmail');
        if ($playerEmail) {
            $player = Entry::find()
                ->section('players')
                ->playerEmail($playerEmail)
                ->one();
            if ($player) {
                return $this->redirect(UrlHelper::siteUrl(''));
            }
        }

        // POST: process email submission
        if (Craft::$app->getRequest()->getIsPost()) {
            $email = Craft::$app->getRequest()->getBodyParam('email');

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->renderTemplate('f1/login', [
                    'error' => 'Please enter a valid email address.',
                    'email' => $email,
                ]);
            }

            // Find the player entry by email on current site
            $player = Entry::find()
                ->section('players')
                ->playerEmail($email)
                ->one();

            if (!$player) {
                return $this->renderTemplate('f1/login', [
                    'error' => 'No player found with that email address.',
                    'email' => $email,
                ]);
            }

            // Store email in session (works across sites)
            Craft::$app->getSession()->set('playerEmail', $email);

            // Redirect to where they were going, or home
            $returnUrl = Craft::$app->getSession()->get('returnUrl', UrlHelper::siteUrl(''));
            Craft::$app->getSession()->remove('returnUrl');
            return $this->redirect($returnUrl);
        }

        // GET: show the login form
        return $this->renderTemplate('f1/login', [
            'error' => null,
            'email' => '',
        ]);
    }

    /**
     * Log out the current player
     */
    public function actionLogout(): Response
    {
        Craft::$app->getSession()->remove('playerEmail');

        // Redirect to the homepage of the current site (not the CP)
        return $this->redirect(Craft::$app->getSites()->getCurrentSite()->getBaseUrl());
    }
}
