<?php

declare(strict_types=1);

/**
 * Supabase PostgREST (REST API) — GET/POST ringan untuk tabel publik (katalog, dll.).
 * Header: apikey + Authorization Bearer = SUPABASE_ANON_KEY (sama seperti Auth).
 */
require_once __DIR__ . '/supabase_auth.php';

/**
 * @param 'GET'|'POST'|'PATCH'|'DELETE' $metode
 * @param array<string, scalar|null> $query Query string (mis. select, order)
 * @return array{ok: bool, http: int, data: mixed, raw: string, curl_errno: int, curl_error: string}
 */
function supabase_rest_request(string $metode, string $path_rel, array $query = [], ?string $body_json = null): array
{
    $base = supabase_url_dasar();
    if ($base === '') {
        return [
            'ok' => false,
            'http' => 0,
            'data' => null,
            'raw' => '',
            'curl_errno' => 0,
            'curl_error' => 'SUPABASE_URL kosong.',
        ];
    }

    $path_rel = '/' . ltrim($path_rel, '/');
    $url = $base . $path_rel;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $headers = supabase_header_curl();
    $headers[] = 'Accept: application/json';

    if ($body_json !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    $metode = strtoupper($metode);
    $opsi = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if ($metode === 'GET') {
        $opsi[CURLOPT_HTTPGET] = true;
    } else {
        $opsi[CURLOPT_CUSTOMREQUEST] = $metode;
        if ($body_json !== null) {
            $opsi[CURLOPT_POSTFIELDS] = $body_json;
        }
    }

    $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($ca && is_readable($ca)) {
        $opsi[CURLOPT_CAINFO] = $ca;
    } else {
        $opsi[CURLOPT_SSL_VERIFYPEER] = false;
        $opsi[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    curl_setopt_array($ch, $opsi);
    $eksekusi = curl_exec($ch);
    $curl_errno = (int) curl_errno($ch);
    $curl_error = (string) curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $raw = is_string($eksekusi) ? $eksekusi : '';
    $data = null;
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        $data = $decoded;
    }

    return [
        'ok' => $curl_errno === 0 && $http >= 200 && $http < 300,
        'http' => $http,
        'data' => $data,
        'raw' => $raw,
        'curl_errno' => $curl_errno,
        'curl_error' => $curl_error,
    ];
}
