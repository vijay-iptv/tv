<?php
error_reporting(0);
date_default_timezone_set('Asia/Kolkata');

$jio_m3u_url = 'https://raw.githubusercontent.com/alex4528/m3u/refs/heads/main/jstar.m3u';
$zee5_m3u_url = 'https://raw.githubusercontent.com/alex4528/m3u/refs/heads/main/z54.m3u';
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
$url = "https://raw.githubusercontent.com/vijay-iptv/tamil/refs/heads/main/ygx.m3u";
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
curl_close($ch);

// Process M3U lines
$combined_m3u = $jiom3u ."\n". $zee5m3u. "\n". $response;
$lines = explode("\n", $combined_m3u);
foreach ($lines as &$line) {
    if (strpos($line, '#EXTINF:') === 0) {
        if (preg_match('/tvg-id="([^"]+)"/', $line, $match)) {
            $id = $match[1];
            if (isset($channelMap[$id])) {
                $logo = $channelMap[$id]['logo'];
                $lang = $channelMap[$id]['language'];
                if (preg_match('/tvg-logo="[^"]*"/', $line))
                {
                    $line = preg_replace('/tvg-logo="[^"]*"/', 'tvg-logo="' . $logo . '"', $line);
                }
                else 
                {
                    $line = preg_replace('/(tvg-id="[^"]+")/', '$1 tvg-logo="' . $logo . '"', $line);
                }
                if (preg_match('/group-title="JioPlus-[^"]*"/', $line) && $channelMap[$id] != '') 
                {
                    $line = preg_replace('/group-title="JioPlus-[^"]*"/', 'group-title="JioPlus-' . $lang . '"', $line);
                }
                else if (preg_match('/group-title="Zee5-[^"]*"/', $line) && $channelMap[$id] != '') 
                {
                    $line = preg_replace('/group-title="Zee5-[^"]*"/', 'group-title="' . $lang . '"', $line);
                }
                else
                {
                    $line = preg_replace('/group-title="[^"]*"/', 'group-title="JioStar-' . $lang . '"', $line);
                }
            }
        }
    }
}
header('Content-Type: text/plain');
echo '#EXTM3U x-tvg-url="https://live.dinesh29.com.np/epg/jiotvplus/master-epg.xml.gz';
echo implode("\n", $lines);

$url = "https://arunjunan20.github.io/My-IPTV/"; // Your API URL
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout in 10 sec
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Max execution time 30 sec

$response = curl_exec($ch);
curl_close($ch);
$response = preg_replace(
    '/tvg-logo\s*=\s*"https:\/\/yt3\.googleusercontent\.com\/GJVGgzRXxK1FDoUpC8ztBHPu81PMnhc8inodKtEckH-rykiYLzg93HUQIoTIirwORynozMkR=s900-c-k-c0x00ffffff-no-rj"/',
    'tvg-logo="https://raw.githubusercontent.com/vijay-iptv/logos/refs/heads/main/Zee_Tamil_News.png"',
    $response
);
echo "$response";

$url = "https://raw.githubusercontent.com/vijay-iptv/tamil/refs/heads/main/iptv.m3u"; // Your API URL
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout in 10 sec
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Max execution time 30 sec

$response = curl_exec($ch);
curl_close($ch);
echo "$response";

$url = "https://raw.githubusercontent.com/geekyhimanshu/Khu/refs/heads/main/Sony%20Channel.m3u"; // Your API URL
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout in 10 sec
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Max execution time 30 sec

$response = curl_exec($ch);

curl_close($ch);
$response = preg_replace(
    '/tvg-logo\s*=\s*"https:\/\/xstreamcp-assets-msp\.streamready\.in\/assets\/LIVETV\/LIVECHANNEL\/LIVETV_LIVETVCHANNEL_SONY_PIX_HD\/images\/LOGO_HD\/image\.png"/',
    'tvg-logo="https://raw.githubusercontent.com/vijay-iptv/logos/refs/heads/main/Sony_Pix_HD.png"',
    $response
);

echo $response;

exit;
?>
