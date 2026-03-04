import "vanilla-cookieconsent/dist/cookieconsent.css";
import * as CookieConsent from "vanilla-cookieconsent";

// obtain cookieconsent plugin
var cc = CookieConsent;

fetch(baseUrl + 'api/cookie-banner.json')
  .then(response => response.json())
  .then(jsonData => {
    var cookieData = jsonData.data[0];

    cc.run({
        cookie: {
            name: "cc_cookie_consent",
        },
    
        guiOptions: {
            consentModal: {
                layout: "box inline",
                position: "bottom right",
                equalWeightButtons: false,
                flipButtons: false,
            },
            preferencesModal: {
                layout: "bar",
                position: "right",
                equalWeightButtons: true,
                flipButtons: false,
            },
        },
    
        categories: {
            necessary: {
                readOnly: true,
            },
            analytics: {},
        },
    
        onFirstConsent: () => {
            if (CookieConsent.acceptedCategory("analytics")) {
                gtag('consent', 'update', { 
                    'ad_user_data': 'granted',
                    'ad_personalization': 'granted',
                    'ad_storage': 'granted',
                    'analytics_storage': 'granted'
                });
            }
        },
    
        onConsent: () => {},
    
        onChange: () => {
            if (CookieConsent.acceptedCategory("analytics")) {
                gtag('consent', 'update', { 
                    'ad_user_data': 'granted',
                    'ad_personalization': 'granted',
                    'ad_storage': 'granted',
                    'analytics_storage': 'granted'
                });
            } else {
                gtag('consent', 'update', {                     
                    'ad_user_data': 'denied',
                    'ad_personalization': 'denied',
                    'ad_storage': 'denied',
                    'analytics_storage': 'denied'
                });

                location.reload();
            }            
        },
    
        categories: {
            necessary: {
                readOnly: true,
                enabled: true,
            },
            analytics: {
                autoClear: {
                    cookies: [
                        {
                            name: /^(_ga|_ga_|_gid)/,
                        },
                    ],
                },
            },
            ads: {},
        },
    
        language: {
            default: "en",
            translations: {
                en: {
                    consentModal: {
                        label: cookieData.consentModal.title,
                        title: cookieData.consentModal.title,
                        description: cookieData.consentModal.description,
                        acceptAllBtn: cookieData.consentModal.acceptAllBtn,
                        showPreferencesBtn: cookieData.consentModal.showPreferencesBtn
                    },
                    preferencesModal: {
                        title: cookieData.preferencesModal.title,
                        acceptAllBtn: cookieData.preferencesModal.acceptAllBtn,
                        savePreferencesBtn: cookieData.preferencesModal.savePreferencesBtn,
                        acceptNecessaryBtn: "acceptNecessaryBtn",
                        closeIconLabel: "closeIconLabel",
                        serviceCounterLabel: "serviceCounterLabel",
                        sections: [
                            {
                                description: cookieData.info.description
                            },
                            {
                                title: cookieData.cookies.necessary.title,
                                description: cookieData.cookies.necessary.description,
                                linkedCategory: "necessary",
                            },
                            {
                                title: cookieData.cookies.analytics.title,
                                description: cookieData.cookies.analytics.description,
                                linkedCategory: "analytics",
                            },
                            {
                                title: cookieData.moreInfo.title,
                                description: cookieData.moreInfo.description
                            },
                        ],
                    },
                },
            },
        },
    });
  })
  .catch(error => {
    console.log('Error:', error);
});

