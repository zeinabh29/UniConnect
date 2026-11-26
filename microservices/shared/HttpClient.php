<?php
/**
 * UniConnect Microservices Architecture
 * Shared REST HTTP Client for Inter-Service & Gateway Communication
 */

class HttpClient {
    /**
     * Executes an HTTP REST request and returns decoded JSON + status code.
     *
     * @param string $method GET|POST|PUT|DELETE|PATCH
     * @param string $url Full URL including query parameters
     * @param array|null $data Request payload (JSON-encoded for write methods)
     * @param array $headers Associative headers or numeric header lines
     * @param int $timeoutSeconds Request timeout
     * @return array{status:int, body:array|null, raw:string, error:string|null}
     */
    public static function request($method, $url, $data = null, $headers = [], $timeoutSeconds = 15) {
        $ch = curl_init();
        $method = strtoupper(trim($method));

        $headerLines = [
            'Accept: application/json',
        ];

        // Normalize associative vs numeric headers
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $headerLines[] = $v;
            } else {
                $headerLines[] = "{$k}: {$v}";
            }
        }

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_POSTFIELDS] = $jsonPayload;
            $headerLines[] = 'Content-Type: application/json';
        } elseif ($data !== null && $method === 'GET' && !empty($data)) {
            $sep = (strpos($url, '?') === false) ? '?' : '&';
            $opts[CURLOPT_URL] = $url . $sep . http_build_query($data);
        }

        $opts[CURLOPT_HTTPHEADER] = $headerLines;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $errMsg = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            return [
                'status' => 503,
                'body'   => [
                    'success' => false,
                    'error'   => 'Service unavailable: ' . ($errMsg ?: 'Connection error'),
                ],
                'raw'    => '',
                'error'  => $errMsg ?: 'cURL error #' . $errno,
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [
                'success' => ($status >= 200 && $status < 300),
                'raw'     => $raw,
                'error'   => ($status >= 400) ? 'Invalid JSON response from service' : null,
            ];
        }

        return [
            'status' => $status > 0 ? $status : 500,
            'body'   => $decoded,
            'raw'    => $raw,
            'error'  => null,
        ];
    }

    public static function get($url, $query = [], $headers = []) {
        return self::request('GET', $url, $query, $headers);
    }

    public static function post($url, $data = [], $headers = []) {
        return self::request('POST', $url, $data, $headers);
    }

    public static function put($url, $data = [], $headers = []) {
        return self::request('PUT', $url, $data, $headers);
    }

    public static function delete($url, $data = null, $headers = []) {
        return self::request('DELETE', $url, $data, $headers);
    }

    /**
     * Convenience wrapper for internal microservice-to-microservice calls.
     */
    public static function internal($method, $url, $data = null, $contextUser = null) {
        $headers = [
            'X-Internal-Service-Key' => INTERNAL_SERVICE_KEY,
        ];
        if (is_array($contextUser) && !empty($contextUser['id'])) {
            $headers['X-User-Id']       = (string)$contextUser['id'];
            $headers['X-User-Role']     = (string)($contextUser['role'] ?? '');
            $headers['X-User-Name']     = (string)($contextUser['name'] ?? '');
            $headers['X-User-Email']    = (string)($contextUser['email'] ?? '');
            $headers['X-Student-Pk']    = (string)($contextUser['student_pk'] ?? '');
            $headers['X-Student-Code']  = (string)($contextUser['student_id'] ?? $contextUser['student_code'] ?? '');
            $headers['X-Student-Dept']  = (string)($contextUser['department'] ?? '');
        }
        return self::request($method, $url, $data, $headers);
    }
}
