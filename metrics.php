<?php
// Prevent search engines from indexing this proxy
header('X-Robots-Tag: noindex, nofollow, noarchive');

// Get the requested path
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Map path to Google target
$targetUrl = '';
if (preg_match('/^gtm\.js$/i', $path)) {
    $targetUrl = 'https://www.googletagmanager.com/gtm.js';
} elseif (preg_match('/^ns\.html$/i', $path)) {
    $targetUrl = 'https://www.googletagmanager.com/ns.html';
} elseif (preg_match('/^gtag\/js$/i', $path)) {
    $targetUrl = 'https://www.googletagmanager.com/gtag/js';
} elseif (preg_match('/^g\/collect$/i', $path)) {
    $targetUrl = 'https://www.google-analytics.com/g/collect';
} else {
    header("HTTP/1.1 404 Not Found");
    echo "Not Found";
    exit;
}

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
curl_setopt($ch, CURLOPT_HEADER, true); // We want headers to extract Content-Type etc.
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
    'User-Agent: ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
    'Accept-Language: ' . (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''),
    'X-Forwarded-For: ' . $clientIp
];

// If POST request (GA4 g/collect uses POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    // Get raw POST data
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    // Forward Content-Type if present
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

// Forward specific headers from Google response
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
