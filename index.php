<?php

require_once __DIR__.'/vendor/autoload.php';

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$app = new Application();

/// APP CONFIGURATION
$app['google_client'] = function(Application $app){

    // Step 1: Configure the client object https://developers.google.com/api-client-library/php/auth/web-app#creatingclient
    $client = new Google_Client();
    $client->setAuthConfig('client_secrets.json');
    $client->setAccessType("offline");        // offline access
    $client->setIncludeGrantedScopes(true);   // incremental auth
    $client->addScope(Google_Service_Plus::USERINFO_EMAIL);

    return $client;
};

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));

$app->register(new Silex\Provider\SessionServiceProvider());


// ROUTING
// Step 2: Redirect to Google's OAuth 2.0 server https://developers.google.com/api-client-library/php/auth/web-app#redirecting
$app->get('/', function(Application $app, Request $request) {
    $app['monolog']->debug('Entered index controller');
    if(!$app['session']->get('access_token')) {
        $google_redirect_url = $app['google_client']->createAuthUrl();
        $app['monolog']->debug(sprintf('"access_token" key is not set in session, so redirecting you now to Google Authentication Consent URI... URL=[%s]', $google_redirect_url));
        return $app->redirect($google_redirect_url);
    }else{
        $app['monolog']->debug('"access_token" key is now set in session, so redirecting you /redirected route');
        return $app->redirect('/redirected');
    }

});

// Step 5: Exchange authorization code for refresh and access tokens https://developers.google.com/api-client-library/php/auth/web-app#exchange-authorization-code
$app->get('/oauth2callback', function(Application $app, Request $request) {
    $app['monolog']->debug('Entered /oauth2callback controller. Will now try to authenticate via backend request to Google and receive access token...');
    $code = $request->query->get('code');

    $app['google_client']->authenticate($code);
    $access_token = $app['google_client']->getAccessToken();

    $app['session']->set('access_token', $access_token);
    $app['monolog']->debug('Saved into the session "access_token" and now redirecting to /redirected route');
    return $app->redirect('/redirected');
});

// Calling Googl APIs https://developers.google.com/api-client-library/php/auth/web-app#callinganapi
$app->get('/redirected', function(Application $app, Request $request){

    $app['monolog']->debug('Entered the /redirected route. Will now try to retrieve your Google Plus account data via backend request to Google and using the acccess_token saved in session...');

    if($app['session']->get('access_token')){
        $app['google_client']->setAccessToken($app['session']->get('access_token')['access_token']);
    }

    $plus = new Google_Service_Plus($app['google_client']);
    return "<pre>". var_export($plus->people->get('me'), true)."</pre>";

});

$app->run();