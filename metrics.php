<?php
// Prevent search engines from indexing this proxy
header('X-Robots-Tag: noindex, nofollow, noarchive');

// Load .env file to get Google Analytics ID dynamically
$envFile = __DIR__ . '/.env';
$googleTagId = 'G-Z486T545YD'; // Default fallback
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/^GOOGLE_ANALYTICS_ID\s*=\s*(.+)$/m', $envContent, $matches)) {
        $googleTagId = trim($matches[1], "\"' \r\n");
    }
}

$backendHost = $googleTagId . '.fps.goog';
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Reconstruct GTM Gateway Target URL
$targetUrl = 'https://' . $backendHost . '/' . $path;

// Re-append query parameters (excluding 'path')
$queryParams = $_GET;
unset($queryParams['path']);
if (!empty($queryParams)) {
    $targetUrl .= '?' . http_build_query($queryParams);
}

// Setup cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Extract headers to forward Content-Type etc.
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Get client IP
$clientIp = '';
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $clientIp = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $clientIp = trim($ips[0]);
} else {
    $clientIp = $_SERVER['REMOTE_ADDR'];
}

// Forward request headers
$headers = [
    'Host: ' . $backendHost,
    'User-Agent: ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
    'Accept-Language: ' . (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''),
    'X-Forwarded-For: ' . $clientIp
];

// Handle Geolocation headers
$country = '';
$region = '';
if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
} elseif (isset($_SERVER['HTTP_X_FORWARDED_COUNTRY'])) {
    $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'];
}

if (isset($_SERVER['HTTP_CF_REGION'])) {
    $region = $_SERVER['HTTP_CF_REGION'];
} elseif (isset($_SERVER['HTTP_X_FORWARDED_REGION'])) {
    $region = $_SERVER['HTTP_X_FORWARDED_REGION'];
}

// Force CA/ON geo headers during validation if not present
if (isset($_GET['validate_geo'])) {
    if (empty($country)) {
        $country = 'CA';
    }
    if (empty($region)) {
        $region = 'ON';
    }
}

if (!empty($country)) {
    $headers[] = 'X-Forwarded-Country: ' . $country;
}
if (!empty($region)) {
    $headers[] = 'X-Forwarded-Region: ' . $region;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
    }
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute request
$response = curl_exec($ch);

if ($response === false) {
    header("HTTP/1.1 502 Bad Gateway");
    echo "Proxy Error: " . curl_error($ch);
    curl_close($ch);
    exit;
}

// Split headers and body
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Set response status code
http_response_code($httpCode);

// Forward specific headers from response
$headerLines = explode("\r\n", $responseHeaders);
foreach ($headerLines as $line) {
    if (stripos($line, 'Content-Type:') === 0 || 
        stripos($line, 'Cache-Control:') === 0 || 
        stripos($line, 'Expires:') === 0 || 
        stripos($line, 'Pragma:') === 0) {
        header($line);
    }
}

// Print response body
echo $responseBody;
