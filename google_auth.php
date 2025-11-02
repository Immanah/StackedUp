<?php
session_start();

// Google OAuth Configuration - REMOVED THE SPACE AFTER CLIENT ID
$clientID = '771207645527-8or8trdlmec62ekl6pj63t8hae975dmi.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/ozyde/google_callback.php';

// Generate state token for security
$state = bin2hex(random_bytes(16));
$_SESSION['oauth2state'] = $state;

function getGoogleAuthUrl($clientID, $redirectUri, $state) {
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    $params = [
        'client_id' => $clientID,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'consent'
    ];
    
    return $authUrl . '?' . http_build_query($params);
}

try {
    $authUrl = getGoogleAuthUrl($clientID, $redirectUri, $state);
    header('Location: ' . $authUrl);
    exit();
} catch (Exception $e) {
    error_log("Google Auth Error: " . $e->getMessage());
    header('Location: register.html?error=google_config');
    exit();
}
?>