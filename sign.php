<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/sign.php'));

// Get parameters
$submission_id = optional_param('id', '', PARAM_RAW);
$template_id = optional_param('template', 0, PARAM_INT);
$action = optional_param('action', 'new', PARAM_ALPHANUM); // 'new' or 'edit'

$useremail = $USER->email;
$username = fullname($USER);

// Validate parameters
if ($action === 'new' && empty($template_id)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('invalid_template', 'local_digisign'), 'error');
    echo '<a href="' . new moodle_url('/local/digisign/index.php') . '" class="btn btn-secondary">← Back to Templates</a>';
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'edit' && empty($submission_id)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('invalid_submission', 'local_digisign'), 'error');
    echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
    echo $OUTPUT->footer();
    exit;
}

// Handle new submission creation
if ($action === 'new') {
    $submission = local_digisign_create_submission($template_id, $useremail, $username);
    
    if (!$submission || !is_array($submission)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('failed_create_submission', 'local_digisign'), 'error');
        echo '<a href="' . new moodle_url('/local/digisign/index.php') . '" class="btn btn-secondary">← Back to Templates</a>';
        echo $OUTPUT->footer();
        exit;
    }
    
    // For new submissions, we need to get the template slug from the template data
    // First, let's get the template details to get the slug
    $templates = local_digisign_fetch_templates();
    $template_slug = null;
    
    foreach ($templates as $template) {
        if ($template['id'] == $template_id) {
            $template_slug = $template['slug'];
            break;
        }
    }
    
    if (!$template_slug) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('failed_create_submission', 'local_digisign'), 'error');
        echo '<a href="' . new moodle_url('/local/digisign/index.php') . '" class="btn btn-secondary">← Back to Templates</a>';
        echo $OUTPUT->footer();
        exit;
    }
    
    $submission_id = $submission['id'] ?? '';
    $embed_url = 'https://sign.operatortraining.academy/d/' . $template_slug;
    $page_title = get_string('sign_new_submission', 'local_digisign');
    
    // Extract and store the temporary submitter slug for this user
    $submitter_slug = null;
    if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
        foreach ($submission['submitters'] as $submitter) {
            if (isset($submitter['email']) && $submitter['email'] === $useremail && 
                isset($submitter['role']) && strtolower($submitter['role']) === 'operator') {
                if (!empty($submitter['slug'])) {
                    $submitter_slug = $submitter['slug'];
                }
                break;
            }
        }
    }
    
    try {
        // Store the temporary submitter slug - it will expire but we need it for immediate use
        local_digisign_record_submission($USER->id, $template_id, $template_slug, $submission_id, $submitter_slug, 'created');
    } catch (Exception $e) {
        local_digisign_log('failed to record submission: ' . $e->getMessage());
    }
    
} else {
    // Handle existing submission
    $submission = local_digisign_get_submission($submission_id);
    
    if (!$submission) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('submission_not_found', 'local_digisign'), 'error');
        echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
        echo $OUTPUT->footer();
        exit;
    }
    
    // Check if this submission belongs to the current user
    $belongs_to_user = false;
    if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
        foreach ($submission['submitters'] as $submitter) {
            if (isset($submitter['email']) && $submitter['email'] === $useremail && 
                isset($submitter['role']) && strtolower($submitter['role']) === 'operator') {
                $belongs_to_user = true;
                break;
            }
        }
    }
    
    if (!$belongs_to_user) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('submission_access_denied', 'local_digisign'), 'error');
        echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
        echo $OUTPUT->footer();
        exit;
    }
    
    // For existing submissions, try to get the submitter slug for the user's specific submission
    $submitter_slug = null;
    if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
        foreach ($submission['submitters'] as $submitter) {
            if (isset($submitter['email']) && $submitter['email'] === $useremail && 
                isset($submitter['role']) && strtolower($submitter['role']) === 'operator') {
                if (!empty($submitter['slug'])) {
                    $submitter_slug = $submitter['slug'];
                }
                break;
            }
        }
    }
    
    // If submitter slug is not available (expired), we need to handle this
    if (!$submitter_slug) {
        // Check if we have a stored submitter slug in our database
        $stored_submission = local_digisign_get_local_submission($submission_id, $USER->id);
        if ($stored_submission && !empty($stored_submission->submitterslug)) {
            $submitter_slug = $stored_submission->submitterslug;
        }
    }
    
    // If still no submitter slug, the submission might have expired or been removed
    if (!$submitter_slug) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('submission_expired', 'local_digisign'), 'error');
        echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
        echo $OUTPUT->footer();
        exit;
    }
    
    $embed_url = 'https://sign.operatortraining.academy/d/' . $submitter_slug;
    $page_title = get_string('edit_submission', 'local_digisign');
}

// Set page title and heading
$PAGE->set_title('OTA Digisign - ' . $page_title);
$PAGE->set_heading('OTA Digisign');

echo $OUTPUT->header();

// Sign form container
echo '<div class="local-digisign-sign-container">';
echo '<div style="margin-bottom: 20px;">';
echo '<h2>' . $page_title . '</h2>';
echo '<p>' . get_string('sign_instructions', 'local_digisign') . '</p>';
echo '</div>';

// DocuSeal form container
echo '<div id="docuseal-form-container" style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #f9f9f9; min-height: 600px;">';
echo '<div id="docuseal-loading" style="text-align: center; padding: 40px;">';
echo '<i class="fas fa-spinner fa-spin fa-2x"></i>';
echo '<p>' . get_string('loading_form', 'local_digisign') . '</p>';
echo '</div>';
echo '<div id="docuseal-form-wrapper" style="display: none;">';
echo '<docuseal-form id="docusealForm" data-src="' . htmlspecialchars($embed_url) . '" data-email="' . htmlspecialchars($useremail) . '"></docuseal-form>';
echo '</div>';
echo '</div>';

// Navigation
echo '<div style="margin-top: 20px;">';
if ($action === 'new') {
    echo '<a href="' . new moodle_url('/local/digisign/index.php') . '" class="btn btn-secondary">← Back to Templates</a>';
} else {
    echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
}
echo '</div>';

echo '</div>';

// JavaScript for form handling
echo '<script src="https://cdn.docuseal.com/js/form.js"></script>';
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("docusealForm");
    var loading = document.getElementById("docuseal-loading");
    var wrapper = document.getElementById("docuseal-form-wrapper");
    
    if (form) {
        // Show form when loaded
        setTimeout(function() {
            loading.style.display = "none";
            wrapper.style.display = "block";
        }, 1000);
        
        // Handle form completion
        form.addEventListener("completed", function(e) {
            console.log("DocuSeal form completed:", e.detail);
            
            // Call server to mark submission as complete
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "' . new moodle_url('/local/digisign/ajax.php') . '", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert("' . get_string('submission_completed', 'local_digisign') . '");
                                window.location.href = "' . new moodle_url('/local/digisign/submissions.php') . '";
                            } else {
                                alert("' . get_string('submission_completion_failed', 'local_digisign') . '");
                            }
                        } catch (e) {
                            alert("' . get_string('submission_completion_failed', 'local_digisign') . '");
                        }
                    } else {
                        alert("' . get_string('submission_completion_failed', 'local_digisign') . '");
                    }
                }
            };
            xhr.send("action=complete_submission&sesskey=' . sesskey() . '&submission_id=' . $submission_id . '");
        });
        
        // Handle form errors
        form.addEventListener("error", function(e) {
            console.error("DocuSeal form error:", e.detail);
            alert("' . get_string('form_error', 'local_digisign') . '");
        });
    }
});
</script>';

echo $OUTPUT->footer();
