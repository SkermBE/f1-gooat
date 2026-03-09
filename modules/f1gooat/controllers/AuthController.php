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
        $request = Craft::$app->getRequest();
        $isAjax = $request->getAcceptsJson();

        // Already logged in — go home
        $playerEmail = Craft::$app->getSession()->get('playerEmail');
        if ($playerEmail) {
            $player = Entry::find()
                ->section('players')
                ->playerEmail($playerEmail)
                ->one();
            if ($player) {
                if ($isAjax) {
                    return $this->asJson(['success' => true]);
                }
                return $this->redirect(UrlHelper::siteUrl(''));
            }
        }

        // POST: process email submission
        if ($request->getIsPost()) {
            $email = $request->getBodyParam('email');

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($isAjax) {
                    return $this->asJson(['success' => false, 'error' => 'Please enter a valid email address.']);
                }
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
                if ($isAjax) {
                    return $this->asJson(['success' => false, 'error' => 'No player found with that email address.']);
                }
                return $this->renderTemplate('f1/login', [
                    'error' => 'No player found with that email address.',
                    'email' => $email,
                ]);
            }

            // Store email in session (works across sites)
            Craft::$app->getSession()->set('playerEmail', $email);

            if ($isAjax) {
                return $this->asJson(['success' => true]);
            }

            return $this->redirect(UrlHelper::siteUrl(''));
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

        // Redirect back to the page the user was on, or homepage
        $redirect = Craft::$app->getRequest()->getQueryParam('redirect');

        if ($redirect && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        return $this->redirect(Craft::$app->getSites()->getCurrentSite()->getBaseUrl());
    }
}
