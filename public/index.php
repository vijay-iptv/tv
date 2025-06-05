<?php
error_reporting(0);
date_default_timezone_set('Asia/Kolkata');

$jio_m3u_url = 'https://raw.githubusercontent.com/alex4528/m3u/refs/heads/main/jstar.m3u';
$zee5_m3u_url = 'https://raw.githubusercontent.com/alex4528/m3u/refs/heads/main/z5.m3u';
$json_url = 'https://raw.githubusercontent.com/vijay-iptv/JSON/refs/heads/main/jiodata.json';

// Load M3U and JSON
$jiom3u = file_get_contents($jio_m3u_url);
$zee5m3u = file_get_contents($zee5_m3u_url);
$json = json_decode(file_get_contents($json_url), true);

// Build lookup map: tvg-id → [logoUrl, channelLanguageId]
$channelMap = [];
foreach ($json as $item) {
    if (isset($item['channel_id'], $item['logoUrl'], $item['channelLanguageId'])) {
        $channelMap[(string)$item['channel_id']] = [
            'logo' => $item['logoUrl'],
            'language' => $item['channelLanguageId']
        ];
    }
}

// Process M3U lines
$combined_m3u = $jiom3u . "\n" . $zee5m3u;
$lines = explode("\n", $combined_m3u);
foreach ($lines as &$line) {
    if (strpos($line, '#EXTINF:') === 0) {
        if (preg_match('/tvg-id="([^"]+)"/', $line, $match)) {
            $id = $match[1];
            if (isset($channelMap[$id])) {
                $logo = $channelMap[$id]['logo'];
                $lang = $channelMap[$id]['language'];

                // Update or insert tvg-logo
                if (preg_match('/tvg-logo="[^"]*"/', $line)) {
                    $line = preg_replace('/tvg-logo="[^"]*"/', 'tvg-logo="' . $logo . '"', $line);
                } else {
                    $line = preg_replace('/(tvg-id="[^"]+")/', '$1 tvg-logo="' . $logo . '"', $line);
                }

                // Update or insert group-title
                if (preg_match('/group-title="[^"]*"/', $line)) {
                    $line = preg_replace('/group-title="[^"]*"/', 'group-title="Jio1-' . $lang . '"', $line);
                } else {
                    $line = preg_replace('/(tvg-logo="[^"]*")/', '$1 group-title="Jio1-' . $lang . '"', $line);
                }
            }
        }
    }
}
header('Content-Type: text/plain');
echo implode("\n", $lines);

$url = "https://m3u.ygxworld.in/p/KzKr3LpT0jEe/playlist.m3u";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_USERAGENT, "TiviMate/4.7.0 Android"); // TiviMate-like agent

$response = curl_exec($ch);
curl_close($ch);
echo $response;
exit;
?>