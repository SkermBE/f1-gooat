<?php

use craft\helpers\App;

return [
	'checkDevServer' => true,
	'devServerInternal' => 'http://localhost:5173',
	'devServerPublic' => preg_replace('/:\d+$/', '', App::env('PRIMARY_SITE_URL')) . ':5173',
	'useDevServer' => App::env('ENVIRONMENT') === 'dev' || App::env('CRAFT_ENVIRONMENT') === 'dev',
	'serverPublic' => '/dist/',
	'manifestPath' => '@webroot/dist/.vite/manifest.json',

	'criticalPath' => '@webroot/dist/criticalcss',
	'criticalSuffix' => '_critical.min.css',
];
