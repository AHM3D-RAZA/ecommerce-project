<?php
// Stripe sandbox settings. The keys live in the project's .env file (see
// .env.example), not in this file.

require_once __DIR__ . '/../core/Env.php';

define('STRIPE_PUBLISHABLE_KEY', Env::get('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_SECRET_KEY', Env::get('STRIPE_SECRET_KEY', ''));
define('STRIPE_CURRENCY', Env::get('STRIPE_CURRENCY', 'usd'));

// Builds an absolute URL to one of the public/ pages, for Stripe's redirects.
// Uses APP_URL from .env if set, otherwise works it out from the current request.
function stripe_return_url($file, array $query = [])
{
    $base = Env::get('APP_URL');

    if (!$base) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    }

    $url = rtrim($base, '/') . '/' . $file;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

// Calls the Stripe REST API and returns the decoded response.
// Stripe wants form-encoded bodies (nested values as a[b][c]=...), not JSON.
function stripe_api($method, $path, array $payload = [])
{
    $method = strtoupper($method);
    $url = 'https://api.stripe.com/v1' . $path;
    $encoded = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . STRIPE_SECRET_KEY],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($method === 'GET' || $method === 'DELETE') {
        if ($encoded !== '') {
            $url .= '?' . $encoded;
        }
    } else {
        $options[CURLOPT_POSTFIELDS] = $encoded;
    }

    // WAMP's PHP often has no CA bundle configured, which makes HTTPS calls fail.
    // Use one from .env / the config folder if we can find it.
    $caCandidates = [
        Env::get('STRIPE_CA_BUNDLE'),
        __DIR__ . '/cacert.pem',
        'C:/wamp64/bin/php/php8.2.29/cacert.pem',
    ];
    foreach ($caCandidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
            $options[CURLOPT_CAINFO] = $candidate;
            break;
        }
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize cURL for Stripe API calls.');
    }
    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Stripe API call failed: ' . $curlErr);
    }

    $decoded = json_decode($raw, true);
    if ($httpCode >= 400) {
        throw new RuntimeException($decoded['error']['message'] ?? ('Stripe API error (HTTP ' . $httpCode . ').'));
    }

    return is_array($decoded) ? $decoded : [];
}
