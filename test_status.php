<?php
/**
 * Test script for template status checking functionality
 * 
 * This script tests the new status checking functions to ensure they work correctly
 * with different submission scenarios.
 */

// Include Moodle config if available
if (file_exists(__DIR__ . '/../../config.php')) {
    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot . '/local/digisign/lib.php');
} else {
    echo "This script must be run from within a Moodle environment.\n";
    exit(1);
}

echo "Template Status Checking Test\n";
echo "============================\n\n";

// Test 1: Get templates
echo "1. Fetching templates...\n";
$templates = local_digisign_fetch_templates(10);

if (empty($templates)) {
    echo "No templates found. Please ensure you have templates in your DocuSeal instance.\n";
    exit(1);
}

echo "Found " . count($templates) . " templates.\n\n";

// Test 2: Get status for each template
echo "2. Checking submission status for each template...\n";
$userid = $USER->id ?? 1; // Fallback to user ID 1 if not logged in

foreach ($templates as $template) {
    $template_id = isset($template['id']) ? $template['id'] : 0;
    $template_name = isset($template['name']) ? $template['name'] : (isset($template['title']) ? $template['title'] : 'Untitled');
    
    if ($template_id > 0) {
        echo "Template: $template_name (ID: $template_id)\n";
        
        $status_info = local_digisign_get_template_submission_status($template_id, $userid);
        
        echo "  Status: " . $status_info['status'] . "\n";
        echo "  Submission ID: " . ($status_info['submission_id'] ?? 'None') . "\n";
        
        if (!empty($status_info['submitters'])) {
            echo "  Submitters:\n";
            foreach ($status_info['submitters'] as $submitter) {
                echo "    - " . $submitter['name'] . " (" . $submitter['email'] . "): " . $submitter['status'] . "\n";
            }
        } else {
            echo "  Submitters: None\n";
        }
        
        echo "\n";
    }
}

// Test 3: Get all statuses at once
echo "3. Getting all template statuses...\n";
$all_statuses = local_digisign_get_all_template_statuses($templates, $userid);

echo "Status summary:\n";
$status_counts = [];
foreach ($all_statuses as $template_id => $status_info) {
    $status = $status_info['status'];
    if (!isset($status_counts[$status])) {
        $status_counts[$status] = 0;
    }
    $status_counts[$status]++;
}

foreach ($status_counts as $status => $count) {
    echo "  $status: $count templates\n";
}

echo "\n";
echo "Test completed!\n";
echo "\n";
echo "Expected behavior:\n";
echo "- 'none': No submission exists for this template\n";
echo "- 'created': Submission created but not yet signed\n";
echo "- 'in_progress': Multiple submitters, some completed, some not\n";
echo "- 'completed': All submitters have completed the document\n";
