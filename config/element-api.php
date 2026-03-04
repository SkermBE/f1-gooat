<?php

use craft\elements\GlobalSet;
use craft\helpers\UrlHelper;

return [
    'endpoints' => [
        'api/cookie-banner.json' => function() {
            return [
                'elementType' => GlobalSet::class,
                'criteria' => ['handle' => 'cookieConsent'],
                'transformer' => function(GlobalSet $globalSet) {
                    return [
                        'consentModal' => array(
                            'title' => $globalSet->ccBannerTitle ?? 'Cookies',
                            'description' => $globalSet->ccBannerText ?? 'Deze site maakt gebruik van cookies om je surfervaring te verbeteren.',
                            'acceptAllBtn' => $globalSet->ccAcceptAllBtn ?? 'Akkoord met alle cookies',
                            'showPreferencesBtn' => $globalSet->ccManageBtn ?? 'Voorkeuren',
                        ),
                        'preferencesModal' => array(
                            'title' => $globalSet->ccBannerTitle ?? 'Cookies',
                            'acceptAllBtn' => $globalSet->ccAcceptAllBtn ?? 'Akkoord met alle cookies',
                            'savePreferencesBtn' => $globalSet->ccSaveBtn ?? 'Bevestig mijn keuze',

                            'acceptNecessaryBtn' => 'Reject all',
                            'closeIconLabel' => 'Close modal',
                            'serviceCounterLabel' => 'Service|Services',
                        ),
                        'cookies' => array(
                            'necessary' => array(
                                'title' => $globalSet->itemCookies[0]->fieldCookieTitel ?? 'Essentiële en functionele cookies',
                                'description' => $globalSet->itemCookies[0]->fieldCookieInfo ?? 'Deze cookies zijn verplicht om ervoor te zorgen dat deze website juist en veilig werkt. Ze verzamelen geen persoonlijke of gevoelige informatie. Er worden ook geen IP-adressen opgeslagen, noch informatie van deze verplichte cookies gedeeld met ons of derde partijen.'
                            ),
                            'analytics' => array(
                                'title' => $globalSet->itemCookies[1]->fieldCookieTitel ?? 'Statistische cookies',
                                'description' => $globalSet->itemCookies[1]->fieldCookieInfo ?? 'Deze cookies helpen ons het gebruikersgedrag op onze website te analyseren door onder meer informatie op te slaan i.v.m. de bezoekfrequentie van elke pagina. Dit is enkel mogelijk nadat je hiervoor je akkoord hebt gegeven.'
                            )
                        ),
                        'info' => array(
                            'description' => $globalSet->ccBannerText ?? 'Deze site maakt gebruik van cookies om je surfervaring te verbeteren.'
                        ),
                        'moreInfo' => array(
                            'title' => $globalSet->ccInfoTitle ?? 'Info',
                            'description' => $globalSet->ccInfoDescription ?? 'Essentiële en functionele cookies kan je niet uitschakelen omdat deze noodzakelijk zijn voor de werking van deze website. Statistische cookies worden enkel na jouw akkoord geplaatst.'
                        )
                    ];
                },
                'paginate' => false
            ];
        }
    ]
];