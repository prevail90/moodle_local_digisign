<?php
// Quick test script to run from CLI: php tests/test_templates.php
// It requests /templates using X-Auth-Token and prints HTTP code + body snippet.

$apiKey = 'YOUR_API_KEY_HERE'; // <-- replace
$apiUrl = 'https://api.docuseal.com/templates?limit=5';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'X-Auth-Token: ' . $apiKey,
        'Accept: application/json'
    ],
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP status: {$code}\n\n";
if ($err) {
    echo "cURL error: {$err}\n";
    exit(1);
}
echo "Response snippet:\n";
echo substr($resp, 0, 4000) . "\n";