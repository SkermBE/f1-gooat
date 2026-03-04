<?php

use craft\helpers\App;
use craft\helpers\MailerHelper;
use craft\mail\transportadapters\Smtp;

// Build base config:
$config = [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'components' => [],
    'modules' => [
        'f1-gooat' => [
            'class' => \modules\f1gooat\Module::class,
        ],
    ],
    'bootstrap' => ['f1-gooat'],
];

// Only register the mailer override in DEV:
if (App::env('CRAFT_DEV_MODE')) {

    $config['components']['mailer'] = function () {
        // Get default mailer config
        $config = App::mailerConfig();

        // Use Mailpit SMTP in dev:
        $adapter = MailerHelper::createTransportAdapter(
            Smtp::class,
            [
                'host' => App::env('SMTP_HOST'),
                'port' => App::env('SMTP_PORT'),
            ]
        );

        // Override the transport:
        $config['transport'] = $adapter->defineTransport();

        return Craft::createObject($config);
    };
}

return $config;
