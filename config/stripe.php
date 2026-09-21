<?php

define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_SECRET_KEY', '');
define('STRIPE_CURRENCY', 'usd');

function stripe_api($method, $path, $payload = [])
{
    $caCandidates = [
        getenv('STRIPE_CA_BUNDLE') ?: null,
        'C:/wamp64/bin/php/php8.2.29/cacert.pem',
        __DIR__ . '/cacert.pem',
        dirname(__DIR__) . '/cacert.pem',
    ];

    $caBundle = null;
    foreach ($caCandidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
            $caBundle = $candidate;
            break;
        }
    }

    $request = function (bool $verifyPeer, int $verifyHost) use ($method, $path, $payload, $caBundle) {
        $ch = curl_init('https://api.stripe.com/v1' . $path);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL for Stripe API calls.');
        }

        $body = null;
        if ($method !== 'GET' && $method !== 'DELETE') {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . STRIPE_SECRET_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyHost,
            CURLOPT_CAINFO => $caBundle ?: null,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException('Stripe API call failed: ' . $curlErr);
        }

        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if ($httpCode >= 400) {
            throw new RuntimeException($decoded['error']['message'] ?? 'Stripe API error.');
        }

        return $decoded ?? [];
    };

    try {
        return $request(true, 2);
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        if (stripos($message, 'SSL certificate problem') !== false || stripos($message, 'unable to get local issuer certificate') !== false) {
            return $request(false, 0);
        }

        throw $e;
    }
}
