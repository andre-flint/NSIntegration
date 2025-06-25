<?php
require_once __DIR__ . '/token_manager.php';
require_once __DIR__ . '/netsuite_api.php';

$clientId = '0d04b387a72628cb3408e18a7dc1b4644eaba3b7008daecd7ffe7533ac8acbb2';
$clientSecret = 'f0ebe7656a671cf92078e90faaffce77efda6cb77bd691d303a196d82406b8ee';
$redirectUri = 'http://localhost';
$accountId = '9245359-sb1';
$authorizationCode = '';

// Initialize Token Manager and get access token
$tokenManager = new TokenManager($clientId, $clientSecret, $redirectUri, $accountId);
$accessToken = $tokenManager->getAccessToken($authorizationCode);

// Initialize API with access token
$nsApi = new NetSuiteAPI($accountId, $accessToken);
