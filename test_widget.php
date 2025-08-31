<?php
/**
 * Test page for DocuSeal form widget
 * 
 * This page tests the DocuSeal form widget directly to ensure it's working.
 */

// Include Moodle config
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot . '/local/digisign/lib.php');
} else {
    echo "This script must be run from within a Moodle environment.\n";
    exit(1);
}

require_login();

echo $OUTPUT->header();
echo '<h1>DocuSeal Form Widget Test</h1>';

// Test 1: Check if we can create a submission
echo '<h2>Test 1: Create Submission</h2>';
$templates = local_digisign_fetch_templates();
if (!empty($templates)) {
    $template = $templates[0]; // Use first template
    echo '<p>Testing with template: ' . htmlspecialchars($template['name']) . ' (ID: ' . $template['id'] . ')</p>';
    
    $submission = local_digisign_create_submission($template['id'], $USER->email, fullname($USER));
    if ($submission) {
        echo '<p>✅ Submission created successfully!</p>';
        
        // Extract submitter slug
        $submitterslug = null;
        if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
            $s0 = reset($submission['submitters']);
            if (!empty($s0['slug'])) {
                $submitterslug = $s0['slug'];
            }
        }
        
        if ($submitterslug) {
            echo '<p>Submitter slug: ' . htmlspecialchars($submitterslug) . '</p>';
            
            // Test 2: Embed URL
            echo '<h2>Test 2: Embed URL</h2>';
            $embedUrl = 'https://sign.operatortraining.academy/sign/' . $submitterslug;
            echo '<p>Embed URL: <code>' . htmlspecialchars($embedUrl) . '</code></p>';
            
            // Test 3: Form Widget
            echo '<h2>Test 3: Form Widget</h2>';
            echo '<p>Testing the DocuSeal form widget:</p>';
            echo '<div style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">';
            echo '<docuseal-form id="testForm" data-src="' . htmlspecialchars($embedUrl) . '" data-email="' . htmlspecialchars($USER->email) . '"></docuseal-form>';
            echo '</div>';
            
            // Test 4: JavaScript
            echo '<h2>Test 4: JavaScript Integration</h2>';
            echo '<button id="testBtn" onclick="testFormWidget()">Test Form Widget</button>';
            echo '<div id="testResult"></div>';
            
            echo '<script src="https://cdn.docuseal.com/js/form.js"></script>';
            echo '<script>
                function testFormWidget() {
                    var result = document.getElementById("testResult");
                    var form = document.getElementById("testForm");
                    
                    if (form) {
                        result.innerHTML = "✅ Form element found";
                        
                        // Test event listener
                        form.addEventListener("completed", function(e) {
                            result.innerHTML += "<br>✅ Form completed event fired: " + JSON.stringify(e.detail);
                        });
                        
                        result.innerHTML += "<br>✅ Event listener attached";
                    } else {
                        result.innerHTML = "❌ Form element not found";
                    }
                }
                
                // Test when page loads
                window.addEventListener("load", function() {
                    setTimeout(function() {
                        var form = document.getElementById("testForm");
                        if (form) {
                            console.log("DocuSeal form loaded successfully");
                        } else {
                            console.log("DocuSeal form not found");
                        }
                    }, 1000);
                });
            </script>';
            
        } else {
            echo '<p>❌ No submitter slug found in submission response</p>';
            echo '<pre>' . htmlspecialchars(print_r($submission, true)) . '</pre>';
        }
    } else {
        echo '<p>❌ Failed to create submission</p>';
    }
} else {
    echo '<p>❌ No templates found</p>';
}

echo '<h2>Debug Information</h2>';
echo '<p>User Email: ' . htmlspecialchars($USER->email) . '</p>';
echo '<p>User Name: ' . htmlspecialchars(fullname($USER)) . '</p>';

$cfg = local_digisign_get_config();
echo '<p>API URL: ' . htmlspecialchars($cfg->api_url) . '</p>';
echo '<p>API Key: ' . (empty($cfg->api_key) ? 'NOT SET' : substr($cfg->api_key, 0, 8) . '...') . '</p>';

echo $OUTPUT->footer();
