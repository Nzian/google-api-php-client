<?php
/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
error_reporting(E_ALL);
include_once __DIR__ . '/../vendor/autoload.php';
include_once "templates/base.php";

echo pageHeader("Retrieving users contacts details");

/*************************************************
 * Ensure you've downloaded your oauth credentials
 ************************************************/
if (!$oauth_credentials = getOAuthCredentialsFile()) {
    echo missingOAuth2CredentialsWarning();
    return;
}

/************************************************
 * NOTICE:
 * The redirect URI is to the current page, e.g:
 * http://localhost:8080/idtoken.php
 ************************************************/
$redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

$client = new Google\Client();
$client->setAuthConfig($oauth_credentials);
$client->setRedirectUri($redirect_uri);
$client->setScopes(Google_Service_PeopleService::CONTACTS_READONLY);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

/************************************************
 * If we're logging out we just need to clear our
 * local access token in this case
 ************************************************/
if (isset($_REQUEST['logout'])) {
    unset($_SESSION['id_token_token']);
}


/************************************************
 * If we have a code back from the OAuth 2.0 flow,
 * we need to exchange that with the
 * Google\Client::fetchAccessTokenWithAuthCode()
 * function. We store the resultant access token
 * bundle in the session, and redirect to ourself.
 ************************************************/
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // store in the session also
    $_SESSION['id_token_token'] = $token;

    // redirect back to the example
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    return;
}

/************************************************
  If we have an access token, we can make
  requests, else we generate an authentication URL.
 ************************************************/
if (
  !empty($_SESSION['id_token_token'])
  && isset($_SESSION['id_token_token']['id_token'])
) {
    $client->setAccessToken($_SESSION['id_token_token']);
} else {
    $authUrl = $client->createAuthUrl();
}

//print_r($_SESSION);
/************************************************
  If we're signed in we can go ahead and retrieve
  the ID token, which is part of the bundle of
  data that is exchange in the authenticate step
  - we only need to do a network call if we have
  to retrieve the Google certificate to verify it,
  and that can be cached.
 ************************************************/
if ($_SESSION['id_token_token']) {
    $client->setAccessToken($_SESSION['id_token_token']);
    $service =  new Google_Service_PeopleService($client);

    // Print the names for up to 10 connections.
    $optParams = array(
      'pageSize' => 10,
      'personFields' => 'names,emailAddresses',
    );
    $results = $service->people_connections->listPeopleConnections('people/me', $optParams);
    
    if (count($results->getConnections()) == 0) {
        print "No connections found.\n";
    } else {
        print "People:\n";
        foreach ($results->getConnections() as $person) {
            //var_dump($person);
            if (count($person->getNames()) == 0) {
                print "No names found for this connection\n";
            } else {
                $names = $person->getNames();
                $emails = $person->getEmailAddresses();
                if (count($emails) > 0) {
                    print_r($emails[0]->value);
                }
                $name = $names[0];
                printf("%s\n", $name->getDisplayName());
            }
        }
    }
} else { ?>
<div class="box">
    <?php if (isset($authUrl)): ?>
    <div class="request">
        <a class='login' href='<?php echo $authUrl; ?>'>Connect </a>
    </div>
    <?php endif ?>
</div>
<?php }
?>

<?php echo pageFooter(__FILE__) ?>