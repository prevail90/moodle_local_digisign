<?php
/**
 * Test script to diagnose DocuSeal API connection issues
 * 
 * This script will help identify if the problem is with:
 * - API key configuration
 * - Network connectivity
 * - API endpoint URL
 * - Authentication
 */

// Include Moodle config if available
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot . '/local/digisign/lib.php');
} else {
    echo "This script must be run from within a Moodle environment.\n";
    exit(1);
}

echo "DocuSeal API Connection Test\n";
echo "============================\n\n";

// Test 1: Check configuration
echo "1. Checking Configuration\n";
echo "-------------------------\n";

$cfg = local_digisign_get_config();
echo "API URL: " . $cfg->api_url . "\n";
echo "API Key: " . (empty($cfg->api_key) ? 'NOT SET' : substr($cfg->api_key, 0, 8) . '...') . "\n\n";

if (empty($cfg->api_key)) {
    echo "❌ ERROR: API key is not configured!\n";
    echo "Please go to Site administration → Plugins → Local plugins → Digisign\n";
    echo "and enter your DocuSeal API key.\n\n";
    exit(1);
}

// Test 2: Test basic connectivity
echo "2. Testing Basic Connectivity\n";
echo "-----------------------------\n";

$test_url = rtrim($cfg->api_url, '/') . '/templates';
echo "Testing URL: $test_url\n";

$ch = curl_init($test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Auth-Token: ' . $cfg->api_key,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response Time: " . round($total_time, 2) . " seconds\n";

if ($curl_error) {
    echo "❌ cURL Error: $curl_error\n\n";
} else {
    echo "✅ cURL request completed successfully\n";
}

if ($http_code === 0) {
    echo "❌ ERROR: No HTTP response received (timeout or connection failed)\n\n";
} elseif ($http_code === 401) {
    echo "❌ ERROR: Authentication failed (401 Unauthorized)\n";
    echo "This usually means:\n";
    echo "- The API key is incorrect\n";
    echo "- The API key has expired\n";
    echo "- The API key doesn't have the required permissions\n\n";
} elseif ($http_code === 403) {
    echo "❌ ERROR: Access forbidden (403 Forbidden)\n";
    echo "This usually means the API key doesn't have permission to access templates.\n\n";
} elseif ($http_code === 404) {
    echo "❌ ERROR: Endpoint not found (404 Not Found)\n";
    echo "This usually means the API URL is incorrect.\n\n";
} elseif ($http_code >= 200 && $http_code < 300) {
    echo "✅ SUCCESS: API responded successfully!\n";
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        echo "Found " . count($data['data']) . " templates\n";
    } else {
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }
} else {
    echo "❌ ERROR: Unexpected HTTP code $http_code\n";
    echo "Response: " . substr($response, 0, 200) . "...\n\n";
}

// Test 3: Test with different timeout settings
echo "3. Testing with Different Timeout Settings\n";
echo "-----------------------------------------\n";

$timeouts = [5, 10, 15, 30];
foreach ($timeouts as $timeout) {
    echo "Testing with $timeout second timeout...\n";
    
    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Auth-Token: ' . $cfg->api_key,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $duration = round($end_time - $start_time, 2);
    echo "  Duration: {$duration}s, HTTP Code: $http_code";
    
    if ($curl_error) {
        echo ", Error: $curl_error";
    }
    echo "\n";
    
    if ($http_code >= 200 && $http_code < 300) {
        echo "  ✅ Success with $timeout second timeout!\n";
        break;
    }
}

echo "\n";
echo "Troubleshooting Tips:\n";
echo "====================\n";
echo "1. Check your API key in Moodle admin settings\n";
echo "2. Verify the API key is valid in your DocuSeal instance\n";
echo "3. Check if your DocuSeal instance is accessible from this server\n";
echo "4. Try increasing the timeout in the plugin settings\n";
echo "5. Check if there are any firewall or proxy issues\n";
