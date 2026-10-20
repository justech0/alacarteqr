<?php
declare(strict_types=1);

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}

// Free translation for short Turkish menu text. Destinations are fixed. User input never selects a host.
function chef_translate_fetch(string $url): ?string
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'ChefMenuAdmin/1.0',
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return is_string($body) && $status === 200 && strlen($body) < 200000 ? $body : null;
}

// Primary: Google's public web endpoint. Fallback: MyMemory free tier.
function chef_translate(string $text, string $target): ?string
{
    $query = rawurlencode($text);
    $body = chef_translate_fetch('https://translate.googleapis.com/translate_a/single?client=gtx&sl=tr&tl=' . $target . '&dt=t&q=' . $query);
    $data = $body !== null ? json_decode($body, true) : null;
    if (is_array($data[0] ?? null)) {
        $out = '';
        foreach ($data[0] as $part) {
            $out .= is_string($part[0] ?? null) ? $part[0] : '';
        }
        if (trim($out) !== '') return trim($out);
    }
    $body = chef_translate_fetch('https://api.mymemory.translated.net/get?langpair=tr|' . $target . '&q=' . $query);
    $data = $body !== null ? json_decode($body, true) : null;
    $out = $data['responseData']['translatedText'] ?? null;
    return is_string($out) && trim($out) !== '' && (int)($data['responseStatus'] ?? 0) === 200 ? trim(html_entity_decode($out, ENT_QUOTES, 'UTF-8')) : null;
}
