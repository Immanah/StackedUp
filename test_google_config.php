<?php
// test_google_config.php
session_start();

$clientID = '771207645527-8or8trdlmec62ekl6pj63t8hae975dmi.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/ozyde/google_callback.php';

echo "<h1>Google OAuth Configuration Test</h1>";

// Check if credentials are set
if (empty($clientID) || $clientID === '771207645527-8or8trdlmec62ekl6pj63t8hae975dmi.apps.googleusercontent.com') {
    echo "<p style='color: red;'>❌ Client ID is not set</p>";
} else {
    echo "<p style='color: green;'>✅ Client ID is set</p>";
}

if (empty($clientSecret) || $clientSecret === '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') {
    echo "<p style='color: red;'>❌ Client Secret is not set</p>";
} else {
    echo "<p style='color: green;'>✅ Client Secret is set</p>";
}

echo "<p>Redirect URI: $redirectUri</p>";

// Test the auth URL generation
$state = bin2hex(random_bytes(16));
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'consent'
]);

echo "<p>Generated Auth URL: <a href='$authUrl' target='_blank'>Test Google Sign-in</a></p>";

echo "<h2>Common Issues:</h2>";
echo "<ul>";
echo "<li>Make sure Redirect URI in Google Console matches exactly: $redirectUri</li>";
echo "<li>Ensure Google+ API is enabled</li>";
echo "<li>Check that your OAuth consent screen is configured</li>";
echo "<li>Verify domain restrictions (if any)</li>";
echo "</ul>";
?>