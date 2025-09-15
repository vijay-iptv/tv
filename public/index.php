<?php
error_reporting(0);
date_default_timezone_set('Asia/Kolkata');
define('TOKEN_EXPIRY_TIME', 7000);
define('KEY_FOLDER', '/var/www/secrets');
function cUrlGetData($url, $headers = null, $post_fields = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if (!empty($post_fields)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $data = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);
    return $data;
}

// Encrypt data
function encrypt_data($data, $key)
{
    $key = (int) $key;
    $encrypted = array_map(function ($char) use ($key) {
        return chr(ord($char) + $key);
    }, str_split($data));
    return base64_encode(implode('', $encrypted));
}

// Decrypt data
function decrypt_data($e_data, $key)
{
    $key = (int) $key;
    $encrypted = base64_decode($e_data);
    $decrypted = array_map(function ($char) use ($key) {
        return chr(ord($char) - $key);
    }, str_split($encrypted));
    return implode('', $decrypted);
}

function getCRED()
{
    $filePath = KEY_FOLDER.'/creds.jtv';
    $key_data = file_get_contents(KEY_FOLDER.'/credskey.jtv');
    return decrypt_data(file_get_contents($filePath), $key_data);
}

function getJioTvData($id)
{
    $cred = getCRED();
    $jio_cred = json_decode($cred, true);
    if (!$jio_cred) {
        return null;
    }

    $user = isset($jio_cred['sessionAttributes']['user']) ? $jio_cred['sessionAttributes']['user'] : array();
    $access_token = isset($jio_cred['authToken']) ? $jio_cred['authToken'] : '';
    $crm = isset($user['subscriberId']) ? $user['subscriberId'] : '';
    $uniqueId = isset($user['unique']) ? $user['unique'] : '';
    $device_id = isset($jio_cred['deviceId']) ? $jio_cred['deviceId'] : '';

    $post_data = http_build_query(array('stream_type' => 'Seek', 'channel_id' => $id));

    $headers = array(
        "Host: jiotvapi.media.jio.com",
        "Content-Type: application/x-www-form-urlencoded",
        "appkey: NzNiMDhlYzQyNjJm",
        "channel_id: $id",
        "userid: $crm",
        "crmid: $crm",
        "deviceId: $device_id",
        "devicetype: phone",
        "isott: true",
        "languageId: 6",
        "lbcookie: 1",
        "os: android",
        "dm: Xiaomi 22101316UP",
        "osversion: 14",
        "srno: 240303144000",
        "accesstoken: $access_token",
        "subscriberid: $crm",
        "uniqueId: $uniqueId",
        "content-length: " . strlen($post_data),
        "usergroup: tvYR7NSNn7rymo3F",
        "User-Agent: okhttp/4.9.3",
        "versionCode: 353",
    );

    $response = cUrlGetData("https://jiotvapi.media.jio.com/playback/apis/v1/geturl", $headers, $post_data);
    return json_decode($response, true);
}
function refresh_jio_token()
{
    $JIO_AUTH = json_decode(getCRED(), true);

    if (!empty($JIO_AUTH)) {
        $ref_TokenApi = "https://auth.media.jio.com/tokenservice/apis/v1/refreshtoken?langId=6";
        $ref_TokenPost = '{"appName":"RJIL_JioTV","deviceId":"' . $JIO_AUTH['deviceId'] . '","refreshToken":"' . $JIO_AUTH['refreshToken'] . '"}';
        $ref_TokenHeads = array(
            "accesstoken: " . $JIO_AUTH['authToken'],
            "uniqueId: " . $JIO_AUTH['sessionAttributes']['user']['unique'],
            "devicetype: phone",
            "versionCode: 331",
            "os: android",
            "Content-Type: application/json"
        );

        $process = curl_init($ref_TokenApi);
        curl_setopt($process, CURLOPT_POST, 1);
        curl_setopt($process, CURLOPT_POSTFIELDS, $ref_TokenPost);
        curl_setopt($process, CURLOPT_HTTPHEADER, $ref_TokenHeads);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_TIMEOUT, 10);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        $ref_data = json_decode(curl_exec($process), true);
        curl_close($process);

        $resp = [
            'status' => 'error',
            'message' => '',
            'authToken' => ''
        ];

        if (isset($ref_data['message']) && !empty($ref_data['message'])) {
            $resp["message"] = "JioTV [OTP Login] - AuthToken Refresh Failed";
        }

        if (isset($ref_data['authToken']) && !empty($ref_data['authToken'])) {
            $resp["status"] = "success";
            $resp["message"] = "JioTV [OTP Login] - AuthToken Refreshed Successfully";
            $resp["authToken"] = $ref_data['authToken'];
        }
    }

    return $resp;
}
function getCookiesFromUrl($url, $headers = [], $post_fields = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,   // follow redirects
        CURLOPT_AUTOREFERER => true,            
    ]);

    if ($post_fields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    curl_close($ch);

    return extractCookies($header);
}
function extractCookies($header)
{
    $cookies = [];
    foreach (explode("\r\n", $header) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $line, $matches)) {
            parse_str($matches[1], $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
    }
    return $cookies;
}
function extractHdneaToken($url)
{
    $parts = parse_url($url);
    if (empty($parts['query'])) {
        return null;
    }

    // Split query into params
    parse_str($parts['query'], $queryParams);

    // Case 1: __hdnea__ exists as a query parameter
    if (!empty($queryParams['__hdnea__'])) {
        return '__hdnea__=' . $queryParams['__hdnea__'];
    }

    // Case 2: The whole query string IS the token
    if (preg_match('/^st=\d+~exp=\d+~acl=.*~hmac=[a-f0-9]+$/', $parts['query'])) {
        return '__hdnea__=' . $parts['query'];
    }

    return null; // nothing valid found
}
$filePath = KEY_FOLDER.'/creds.jtv';
$TokenNeedsRefresh = !file_exists($filePath) || (time() - filemtime($filePath) > TOKEN_EXPIRY_TIME);
if ($TokenNeedsRefresh) {
    $new_auth = refresh_jio_token();
    $old_data = getCRED();
    $old_data = json_decode($old_data, true);
    $old_data["authToken"] = $new_auth["authToken"];
    $new_auth = json_encode($old_data);
    $key_data = file_get_contents(KEY_FOLDER."/credskey.jtv");
    file_put_contents(KEY_FOLDER."/creds.jtv", encrypt_data($new_auth, $key_data));
}
$jsonData = getJioTvData(896);
list($baseUrl, $query) = explode('?', $jsonData['result'], 2);
$cookies_y = strpos($query, "minrate=") ? explode("&", $query)[2] : $query;
$chs = explode('/', $baseUrl);
$headers = [
    'Cookie: ' . $cookies_y,
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7'
];
//$cookiesdata = getCookiesFromUrl($jsonData['result'], $headers);
$cooKieesData = extractHdneaToken($jsonData['result']);
$cooKiee = '__hdnea__=' . $cookiesdata['__hdnea__'];

echo '<pre>';
print_r($jsonData).'<br>';
print_r($cooKieesData);
print_r($cookiesdata);
echo '<pre>';exit;

//$jio_m3u_url = 'https://raw.githubusercontent.com/alex8875/m3u/refs/heads/main/jstar.m3u';
$zee5_m3u_url = 'https://raw.githubusercontent.com/alex8875/m3u/refs/heads/main/z5.m3u';
$json_url = 'https://raw.githubusercontent.com/vijay-iptv/JSON/refs/heads/main/jiodata.json';

// Load M3U and JSON
//$jiom3u = file_get_contents($jio_m3u_url);
$zee5m3u = file_get_contents($zee5_m3u_url);
$json = json_decode(file_get_contents($json_url), true);

$output = '#EXTM3U x-tvg-url="https://avkb.short.gy/jioepg.xml.gz"' . PHP_EOL;
foreach ($json as $item) {
    if (isset($item['channel_id'], $item['logoUrl'], $item['channelLanguageId'])) {
        $channelMap[(string)$item['channel_id']] = [
            'logo' => $item['logoUrl'],
            'language' => $item['channelLanguageId']
        ];
    }
    if (isset($item['channel_id'], $item['logoUrl'], $item['channelLanguageId'], $item['channel_name'], $item['license_key'], $item['bts'])) 
    {
        $output .= '#EXTINF:-1 tvg-id="' . $item['channel_id'] . '" group-title="JioPlus-' . $item['channelLanguageId'] . '" tvg-logo="' . $item['logoUrl'] . '",' . $item['channel_name'] . PHP_EOL;
        $output .= '#KODIPROP:inputstream.adaptive.license_type=clearkey' . PHP_EOL;
        $output .= '#KODIPROP:inputstream.adaptive.license_key=' . $item['license_key'] . PHP_EOL;
        $output .= '#EXTVLCOPT:http-user-agent=plaYtv/7.1.3 (Linux;Android 13) ygx/69.1 ExoPlayerLib/824.0' . PHP_EOL;
        $output .= '#EXTHTTP:{"cookie":"'.$cooKiee.'"}'  . PHP_EOL;
        $output .= 'https://jiotvmblive.cdn.jio.com/bpk-tv/' . $item['bts'] . '/index.mpd'.$cooKiee.'&xxx=%7Ccookie='.$cooKiee . PHP_EOL . PHP_EOL;
    }
}
// Process M3U lines
$combined_m3u = $zee5m3u;
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
                if (preg_match('/group-title="Zee5-[^"]*"/', $line) && $channelMap[$id] != '') 
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
echo '#EXTM3U x-tvg-url="https://live.dinesh29.com.np/epg/jiotvplus/master-epg.xml.gz \n';
echo $output . PHP_EOL . PHP_EOL;
echo implode("\n", $lines);

$dinesh_url = "http://live.dinesh29.com.np/jiotvplus.m3u"; // Your API URL
$ch = curl_init(); 
curl_setopt($ch, CURLOPT_URL, $dinesh_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$m3u = curl_exec($ch);
curl_close($ch);
foreach ($json as $channel) {
    $tvgId = strtolower(str_replace(' ', '', $channel['channel_name']));
    
    // Match EXTINF line with this tvg-id
    $pattern = '/(#EXTINF:-1\s+tvg-id="'.$tvgId.'".*?),(.*)/i';
    
    if (preg_match($pattern, $m3u, $matches)) {
        // Build replacement EXTINF line
        $replacement = '#EXTINF:-1 tvg-id="'.$tvgId.'" tvg-logo="'.$channel['logoUrl'].'" group-title="JioPlus2-'.$channel['channelLanguageId'].'",'.$channel['channel_name'];
        
        // Replace in whole playlist
        $m3u = str_replace($matches[0], $replacement, $m3u);
    }
}

header('Content-Type: text/plain');
echo $m3u;

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
$response = preg_replace(
    '/https:\/\/d229kpbsb5jevy\.cloudfront\.net\/timesplay\/content\/common\/logos\/channel\/logos\/wthfwe\.jpeg/',
    'https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-12095-j9ooixfs-v1/imageContent-12095-j9ooixfs-m1.png',
    $response
);
$response = preg_replace(
    '/https:\/\/images\.now-tv\.com\/shares\/channelPreview\/img\/en_hk\/color\/ch115_160_115/',
    'https://raw.githubusercontent.com/vijay-iptv/logos/refs/heads/main/HBO.png',
    $response
);
$response = preg_replace(
    '/https:\/\/resizer-acm\.eco\.astro\.com\.my\/tr:w-256,q:85\/https:\/\/divign0fdw3sv\.cloudfront\.net\/Images\/ChannelLogo\/contenthub\/337_144\.png/',
    'https://raw.githubusercontent.com/vijay-iptv/logos/refs/heads/main/Cinemax.png',
    $response
);
$response = preg_replace(
    '/https:\/\/d229kpbsb5jevy\.cloudfront\.net\/timesplay\/content\/common\/logos\/channel\/logos\/vunjev\.jpeg/',
    'https://raw.githubusercontent.com/vijay-iptv/logos/refs/heads/main/MNX_HD.png',
    $response
);
$response = preg_replace(
    '/https:\/\/d229kpbsb5jevy\.cloudfront\.net\/timesplay\/content\/common\/logos\/channel\/logos\/leazcc\.jpeg/',
    'https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-826-j5m9kx5c-v1/imageContent-826-j5m9kx5c-m1.png',
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
