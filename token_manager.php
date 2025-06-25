<?php
class TokenManager {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $accountId;

    public function __construct($clientId, $clientSecret, $redirectUri, $accountId) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->accountId = $accountId;
    }

    private function tokenRequest($postFields) {
        $tokenUrl = "https://{$this->accountId}.suitetalk.api.netsuite.com/services/rest/auth/oauth2/v1/token";

        $headers = [
            'Authorization: Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // For sandbox/testing only - remove or set to true for production
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('cURL error during token request: ' . curl_error($ch));
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception("Token request failed. HTTP Code: $http_code Response: $response");
        }

        return json_decode($response, true);
    }

    public function getAccessToken($authorizationCode = '') {
        // Load saved tokens and expiry
        $accessTokenFile = __DIR__ . '/access_token.txt';
        $refreshTokenFile = __DIR__ . '/refresh_token.txt';
        $expiryFile = __DIR__ . '/token_expiry.txt';

        $accessToken = file_exists($accessTokenFile) ? trim(file_get_contents($accessTokenFile)) : null;
        $refreshToken = file_exists($refreshTokenFile) ? trim(file_get_contents($refreshTokenFile)) : null;
        $expiry = file_exists($expiryFile) ? (int)trim(file_get_contents($expiryFile)) : 0;
        $now = time();

        if (!empty($authorizationCode)) {
            // Exchange authorization code for tokens (first time)
            $postFields = [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $this->redirectUri,
            ];
            $tokenData = $this->tokenRequest($postFields);
        } elseif (!empty($refreshToken) && ($now >= $expiry)) {
            // Use refresh token to get new access token
            $postFields = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ];
            $tokenData = $this->tokenRequest($postFields);
        } elseif (!empty($accessToken) && $now < $expiry) {
            // Token still valid
            return $accessToken;
        } else {
            throw new Exception('No valid authorization code or refresh token available.');
        }

        if (!isset($tokenData['access_token'])) {
            throw new Exception('No access token in token response.');
        }

        // Save new tokens and expiry
        file_put_contents($accessTokenFile, $tokenData['access_token']);
        if (isset($tokenData['refresh_token'])) {
            file_put_contents($refreshTokenFile, $tokenData['refresh_token']);
        }
        if (isset($tokenData['expires_in'])) {
            file_put_contents($expiryFile, $now + $tokenData['expires_in'] - 30);
        }

        return $tokenData['access_token'];
    }
}
