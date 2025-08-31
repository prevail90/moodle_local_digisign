<?php
/**
 * Test script for DocuSeal API integration
 * 
 * This script tests the API integration using the exact patterns provided by the user.
 * It can be run independently to verify the API connection works.
 */

// Include Moodle config if available, otherwise set up basic config
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot . '/local/digisign/lib.php');
} else {
    // Standalone mode - set up basic configuration
    define('MOODLE_INTERNAL', true);
    
    // You can override these values for testing
    $test_config = [
        'api_key' => 'YOUR_API_KEY_HERE',
        'api_url' => 'https://api.docuseal.com'
    ];
    
    // Simple config function for standalone testing
    function local_digisign_get_config() {
        global $test_config;
        return (object)$test_config;
    }
}

echo "DocuSeal API Integration Test\n";
echo "============================\n\n";

// Test 1: PHP SDK - List Templates
echo "1. Testing PHP SDK - List Templates\n";
echo "-----------------------------------\n";

try {
    $cfg = local_digisign_get_config();
    
    // Check if SDK is available
    if (class_exists('\\Docuseal\\Api')) {
        $docuseal = new \Docuseal\Api($cfg->api_key, $cfg->api_url);
        $templates = $docuseal->listTemplates(['limit' => 5]);
        
        if (is_array($templates) && isset($templates['data'])) {
            echo "✓ SDK Templates: Found " . count($templates['data']) . " templates\n";
            foreach (array_slice($templates['data'], 0, 3) as $template) {
                $name = isset($template['name']) ? $template['name'] : (isset($template['title']) ? $template['title'] : 'Untitled');
                echo "  - " . $name . "\n";
            }
        } else {
            echo "✗ SDK Templates: Unexpected response format\n";
        }
    } else {
        echo "✗ SDK Templates: Docuseal SDK not available\n";
    }
} catch (Exception $e) {
    echo "✗ SDK Templates Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: PHP SDK - List Submissions
echo "2. Testing PHP SDK - List Submissions\n";
echo "-------------------------------------\n";

try {
    if (class_exists('\\Docuseal\\Api')) {
        $docuseal = new \Docuseal\Api($cfg->api_key, $cfg->api_url);
        $submissions = $docuseal->listSubmissions(['limit' => 5]);
        
        if (is_array($submissions) && isset($submissions['data'])) {
            echo "✓ SDK Submissions: Found " . count($submissions['data']) . " submissions\n";
            foreach (array_slice($submissions['data'], 0, 3) as $submission) {
                $status = isset($submission['status']) ? $submission['status'] : 'Unknown';
                echo "  - ID: " . (isset($submission['id']) ? $submission['id'] : 'N/A') . ", Status: $status\n";
            }
        } else {
            echo "✗ SDK Submissions: Unexpected response format\n";
        }
    } else {
        echo "✗ SDK Submissions: Docuseal SDK not available\n";
    }
} catch (Exception $e) {
    echo "✗ SDK Submissions Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: cURL - List Templates
echo "3. Testing cURL - List Templates\n";
echo "--------------------------------\n";

try {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $cfg->api_url . "/templates",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: " . $cfg->api_key
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($err) {
        echo "✗ cURL Templates Error: " . $err . "\n";
    } else {
        if ($http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            if ($data && isset($data['data'])) {
                echo "✓ cURL Templates: Found " . count($data['data']) . " templates (HTTP $http_code)\n";
                foreach (array_slice($data['data'], 0, 3) as $template) {
                    $name = isset($template['name']) ? $template['name'] : (isset($template['title']) ? $template['title'] : 'Untitled');
                    echo "  - " . $name . "\n";
                }
            } else {
                echo "✗ cURL Templates: Unexpected response format (HTTP $http_code)\n";
                echo "  Response: " . substr($response, 0, 200) . "...\n";
            }
        } else {
            echo "✗ cURL Templates: HTTP Error $http_code\n";
            echo "  Response: " . substr($response, 0, 200) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "✗ cURL Templates Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: cURL - List Submissions
echo "4. Testing cURL - List Submissions\n";
echo "----------------------------------\n";

try {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $cfg->api_url . "/submissions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: " . $cfg->api_key
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($err) {
        echo "✗ cURL Submissions Error: " . $err . "\n";
    } else {
        if ($http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);
            if ($data && isset($data['data'])) {
                echo "✓ cURL Submissions: Found " . count($data['data']) . " submissions (HTTP $http_code)\n";
                foreach (array_slice($data['data'], 0, 3) as $submission) {
                    $status = isset($submission['status']) ? $submission['status'] : 'Unknown';
                    echo "  - ID: " . (isset($submission['id']) ? $submission['id'] : 'N/A') . ", Status: $status\n";
                }
            } else {
                echo "✗ cURL Submissions: Unexpected response format (HTTP $http_code)\n";
                echo "  Response: " . substr($response, 0, 200) . "...\n";
            }
        } else {
            echo "✗ cURL Submissions: HTTP Error $http_code\n";
            echo "  Response: " . substr($response, 0, 200) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "✗ cURL Submissions Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Plugin Functions (if in Moodle context)
if (function_exists('local_digisign_fetch_templates')) {
    echo "5. Testing Plugin Functions\n";
    echo "---------------------------\n";
    
    try {
        $templates = local_digisign_fetch_templates(5);
        echo "✓ Plugin Templates: Found " . count($templates) . " templates\n";
        
        $submissions = local_digisign_fetch_submissions(5);
        echo "✓ Plugin Submissions: Found " . count($submissions) . " submissions\n";
        
    } catch (Exception $e) {
        echo "✗ Plugin Functions Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "5. Plugin Functions: Not available (not in Moodle context)\n";
}

echo "\n";
echo "Test completed!\n";
echo "\n";
echo "To run this test:\n";
echo "1. Make sure you have the Docuseal PHP SDK installed: composer require docusealco/docuseal-php\n";
echo "2. Update the API key in the script or configure it in Moodle admin\n";
echo "3. Run: php test_api.php\n";
