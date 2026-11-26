<?php
/**
 * UniConnect Microservices Architecture
 * Pure-PHP HMAC-SHA256 JWT Helper (Zero External Dependencies)
 */

class JwtHelper {
    public static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generates a signed JWT token.
     */
    public static function encode(array $payload, $ttlSeconds = null) {
        $ttl = $ttlSeconds ?? JWT_TTL_SECONDS;
        $now = time();

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['exp'] = $payload['exp'] ?? ($now + $ttl);
        if (empty($payload['jti'])) {
            $payload['jti'] = bin2hex(random_bytes(16));
        }

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Verifies and decodes a JWT token. Returns payload array or null on failure.
     */
    public static function decode($jwt) {
        if (!is_string($jwt) || trim($jwt) === '') {
            return null;
        }

        $parts = explode('.', trim($jwt));
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $headerJson  = self::base64UrlDecode($headerB64);
        $payloadJson = self::base64UrlDecode($payloadB64);
        $signature   = self::base64UrlDecode($sigB64);

        $header  = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, JWT_SECRET, true);
        if (!hash_equals($expectedSig, (string)$signature)) {
            return null;
        }

        if (isset($payload['exp']) && time() > (int)$payload['exp']) {
            return null;
        }

        return $payload;
    }

    /**
     * Extracts Bearer token from incoming HTTP request headers.
     */
    public static function extractBearerToken() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTH_TOKEN']
            ?? '';

        if ($authHeader === '' && function_exists('apache_request_headers')) {
            $reqHeaders = apache_request_headers();
            foreach ($reqHeaders as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0 || strcasecmp($k, 'X-Auth-Token') === 0) {
                    $authHeader = $v;
                    break;
                }
            }
        }

        if (preg_match('/Bearer\s+(.+)$/i', trim((string)$authHeader), $matches)) {
            return trim($matches[1]);
        }

        return !empty($_SERVER['HTTP_X_AUTH_TOKEN']) ? trim($_SERVER['HTTP_X_AUTH_TOKEN']) : null;
    }
}
