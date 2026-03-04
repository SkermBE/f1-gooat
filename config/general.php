<?php

/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see \craft\config\GeneralConfig
 * @link https://craftcms.com/docs/5.x/reference/config/general.html
 */

use craft\config\GeneralConfig;
use craft\helpers\App;

return GeneralConfig::create()
    // Set the @webroot alias so the clear-caches command knows where to find CP resources
    ->aliases([
        '@webroot' => dirname(__DIR__) . '/web',
        '@web' => rtrim(APP::env('PRIMARY_SITE_URL'), '/'),
    ])

    // Disable X-Powered-By: Craft CMS tag for beter security
    ->sendPoweredByHeader(false)

    // Set the default week start day for date pickers (0 = Sunday, 1 = Monday, etc.)
    ->defaultWeekStartDay(1)

    // Prevent generated URLs from including "index.php"
    ->omitScriptNameInUrls()

    // Routing to the error pages
    ->errorTemplatePrefix("_errors/")

    // Preload Single entries as Twig variables
    ->preloadSingles()

    // Change max revistions to 8
    ->maxRevisions(8)

    // Prevent user enumeration attacks
    ->preventUserEnumeration()
    
    // converted to ASCII (i.e. ñ → n) -> only affects the JavaScript auto-generated slugs
    ->limitAutoSlugsToAscii(true)
    
    // Search words to left and right aswell
    ->defaultSearchTermOptions([
        'subLeft' => true,
        'subRight' => true,
    ])

    // Extending file kinds
    ->extraFileKinds([
        'svg' => [
            'label' => 'SVG',
            'extensions' => ['svg']
        ]
    ])
;
