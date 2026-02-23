<?php
declare(strict_types=1);

// Minimal GitHub OAuth token exchange endpoint for FSNotes.
// Deploy behind HTTPS.
//
// Expected JSON POST body:
// {
//   "code": "...",
//   "code_verifier": "...",
//   "redirect_uri": "fsnotes://oauth/callback"
// }
//
// Response: raw GitHub JSON

// Load .env from same directory (simple parser, no dependency).
$envPath = __DIR__ . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($value !== '' && (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'method_not_allowed',
        'error_description' => 'Use POST'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

$code = is_array($data) ? ($data['code'] ?? null) : null;
$codeVerifier = is_array($data) ? ($data['code_verifier'] ?? null) : null;
$redirectUri = is_array($data) ? ($data['redirect_uri'] ?? null) : null;

if (!$code || !$codeVerifier || !$redirectUri) {
    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_request',
        'error_description' => 'code, code_verifier and redirect_uri are required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Set these on your server and never commit secrets.
$clientId = getenv('GITHUB_CLIENT_ID') ?: '';
$clientSecret = getenv('GITHUB_CLIENT_SECRET') ?: '';

if ($clientId === '' || $clientSecret === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'server_misconfigured',
        'error_description' => 'Missing GITHUB_CLIENT_ID or GITHUB_CLIENT_SECRET'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$postFields = http_build_query([
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'code_verifier' => $codeVerifier
]);

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20
]);

$resp = curl_exec($ch);
$curlErr = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'upstream_error',
        'error_description' => $curlErr ?: 'GitHub token exchange failed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($status > 0 ? $status : 200);
echo $resp;
