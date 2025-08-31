<?php
/**
 * Example usage of local_digisign functions
 * 
 * This file demonstrates how to use the Docuseal API integration functions
 * that work with both the PHP SDK and cURL fallback.
 * 
 * Usage: Include this file in a Moodle context or call the functions directly.
 */

// Example 1: Fetch templates using the plugin function
function example_fetch_templates() {
    echo "=== Fetching Templates ===\n";
    
    // This will try SDK first, then fall back to cURL
    $templates = local_digisign_fetch_templates(10);
    
    if (empty($templates)) {
        echo "No templates found or error occurred.\n";
        return;
    }
    
    echo "Found " . count($templates) . " templates:\n";
    foreach ($templates as $template) {
        $name = isset($template['name']) ? $template['name'] : (isset($template['title']) ? $template['title'] : 'Untitled');
        $id = isset($template['id']) ? $template['id'] : 'N/A';
        echo "- ID: $id, Name: $name\n";
    }
    echo "\n";
}

// Example 2: Fetch submissions using the plugin function
function example_fetch_submissions() {
    echo "=== Fetching Submissions ===\n";
    
    // Fetch all submissions
    $submissions = local_digisign_fetch_submissions(10);
    
    if (empty($submissions)) {
        echo "No submissions found or error occurred.\n";
        return;
    }
    
    echo "Found " . count($submissions) . " submissions:\n";
    foreach ($submissions as $submission) {
        $id = isset($submission['id']) ? $submission['id'] : 'N/A';
        $status = isset($submission['status']) ? $submission['status'] : 'Unknown';
        echo "- ID: $id, Status: $status\n";
    }
    echo "\n";
}

// Example 3: Fetch submissions with filters
function example_fetch_submissions_with_filters() {
    echo "=== Fetching Completed Submissions ===\n";
    
    // Fetch only completed submissions
    $filters = ['status' => 'completed'];
    $submissions = local_digisign_fetch_submissions(5, $filters);
    
    if (empty($submissions)) {
        echo "No completed submissions found.\n";
        return;
    }
    
    echo "Found " . count($submissions) . " completed submissions:\n";
    foreach ($submissions as $submission) {
        $id = isset($submission['id']) ? $submission['id'] : 'N/A';
        $created = isset($submission['created_at']) ? date('Y-m-d', strtotime($submission['created_at'])) : 'N/A';
        echo "- ID: $id, Created: $created\n";
    }
    echo "\n";
}

// Example 4: Get a specific submission
function example_get_submission($submission_id) {
    echo "=== Getting Specific Submission ===\n";
    
    $submission = local_digisign_get_submission($submission_id);
    
    if (!$submission) {
        echo "Submission not found or error occurred.\n";
        return;
    }
    
    echo "Submission details:\n";
    echo "- ID: " . (isset($submission['id']) ? $submission['id'] : 'N/A') . "\n";
    echo "- Status: " . (isset($submission['status']) ? $submission['status'] : 'N/A') . "\n";
    echo "- Created: " . (isset($submission['created_at']) ? $submission['created_at'] : 'N/A') . "\n";
    echo "\n";
}

// Example 5: Direct PHP SDK usage for templates (exact pattern from user)
function example_sdk_templates() {
    echo "=== Direct SDK Templates Example ===\n";
    
    if (!local_digisign_ensure_autoload() || !class_exists('\\Docuseal\\Api')) {
        echo "SDK not available\n";
        return;
    }
    
    try {
        $cfg = local_digisign_get_config();
        $docuseal = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
        $templates = $docuseal->listTemplates(['limit' => 10]);
        
        if (is_array($templates) && isset($templates['data'])) {
            echo "Direct SDK found " . count($templates['data']) . " templates\n";
        }
    } catch (Exception $e) {
        echo "Direct SDK error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Example 6: Direct PHP SDK usage for submissions (exact pattern from user)
function example_sdk_submissions() {
    echo "=== Direct SDK Submissions Example ===\n";
    
    if (!local_digisign_ensure_autoload() || !class_exists('\\Docuseal\\Api')) {
        echo "SDK not available\n";
        return;
    }
    
    try {
        $cfg = local_digisign_get_config();
        $docuseal = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
        $submissions = $docuseal->listSubmissions(['limit' => 10]);
        
        if (is_array($submissions) && isset($submissions['data'])) {
            echo "Direct SDK found " . count($submissions['data']) . " submissions\n";
        }
    } catch (Exception $e) {
        echo "Direct SDK error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Example 7: Direct cURL usage for templates (exact pattern from user)
function example_curl_templates() {
    echo "=== Direct cURL Templates Example ===\n";
    
    $cfg = local_digisign_get_config();
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => rtrim($cfg->api_url, '/') . "/templates",
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
    
    curl_close($curl);
    
    if ($err) {
        echo "cURL Error #:" . $err . "\n";
    } else {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            echo "Direct cURL found " . count($data['data']) . " templates\n";
        } else {
            echo "Direct cURL response: " . substr($response, 0, 200) . "...\n";
        }
    }
    echo "\n";
}

// Example 8: Direct cURL usage for submissions (exact pattern from user)
function example_curl_submissions() {
    echo "=== Direct cURL Submissions Example ===\n";
    
    $cfg = local_digisign_get_config();
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => rtrim($cfg->api_url, '/') . "/submissions",
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
    
    curl_close($curl);
    
    if ($err) {
        echo "cURL Error #:" . $err . "\n";
    } else {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            echo "Direct cURL found " . count($data['data']) . " submissions\n";
        } else {
            echo "Direct cURL response: " . substr($response, 0, 200) . "...\n";
        }
    }
    echo "\n";
}

// Example 9: Simple cURL command examples (for reference)
function example_curl_commands() {
    echo "=== cURL Command Examples ===\n";
    echo "List templates:\n";
    echo "curl --request GET \\\n";
    echo "  --url https://api.docuseal.com/templates \\\n";
    echo "  --header 'X-Auth-Token: API_KEY'\n\n";
    
    echo "List submissions:\n";
    echo "curl --request GET \\\n";
    echo "  --url https://api.docuseal.com/submissions \\\n";
    echo "  --header 'X-Auth-Token: API_KEY'\n\n";
}

// Run examples if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    // Note: These examples require Moodle context and proper configuration
    echo "DocuSeal API Integration Examples\n";
    echo "================================\n\n";
    
    // Uncomment the examples you want to run:
    // example_fetch_templates();
    // example_fetch_submissions();
    // example_fetch_submissions_with_filters();
    // example_get_submission(123); // Replace with actual submission ID
    // example_sdk_templates();
    // example_sdk_submissions();
    // example_curl_templates();
    // example_curl_submissions();
    // example_curl_commands();
    
    echo "To run examples, uncomment the function calls above.\n";
    echo "Make sure you have configured the DocuSeal API key in Moodle admin.\n";
}
