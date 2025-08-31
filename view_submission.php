<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/view_submission.php'));
$PAGE->set_title('OTA Digisign - View Submission');
$PAGE->set_heading('OTA Digisign - View Submission');

// Get submission ID
$submission_id = required_param('id', PARAM_RAW);

// Get submission details
$submission = local_digisign_get_submission($submission_id);

if (!$submission) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('submission_not_found', 'local_digisign'), 'error');
    echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
    echo $OUTPUT->footer();
    exit;
}

// Check if this submission belongs to the current user
$useremail = $USER->email;
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

echo $OUTPUT->header();

// Submission details
echo '<div class="local-digisign-submission-details">';

// Header with actions
echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
echo '<h2>' . get_string('submission_details', 'local_digisign') . '</h2>';
echo '<div>';

// Edit button (if not completed)
if ($submission['status'] !== 'completed') {
    echo '<button class="btn btn-primary edit-submission" data-id="' . s($submission_id) . '">Edit Submission</button>';
}

// Download button (if completed)
if ($submission['status'] === 'completed') {
    echo '<a href="' . new moodle_url('/local/digisign/download.php', ['id' => $submission_id]) . '" class="btn btn-success">Download PDF</a>';
}

echo '</div>';
echo '</div>';

// Basic information
echo '<div class="card" style="margin-bottom: 20px;">';
echo '<div class="card-header">';
echo '<h3>Basic Information</h3>';
echo '</div>';
echo '<div class="card-body">';
echo '<table class="table table-borderless">';

$status_class = 'badge badge-secondary';
if ($submission['status'] === 'completed') {
    $status_class = 'badge badge-success';
} elseif ($submission['status'] === 'pending') {
    $status_class = 'badge badge-warning';
} elseif ($submission['status'] === 'draft') {
    $status_class = 'badge badge-info';
}

echo '<tr><td><strong>Status:</strong></td><td><span class="' . $status_class . '">' . s($submission['status']) . '</span></td></tr>';
echo '<tr><td><strong>Submission ID:</strong></td><td>' . s($submission_id) . '</td></tr>';

if (!empty($submission['created_at'])) {
    echo '<tr><td><strong>Created:</strong></td><td>' . date('Y-m-d H:i:s', strtotime($submission['created_at'])) . '</td></tr>';
}
if (!empty($submission['updated_at'])) {
    echo '<tr><td><strong>Updated:</strong></td><td>' . date('Y-m-d H:i:s', strtotime($submission['updated_at'])) . '</td></tr>';
}

echo '</table>';
echo '</div>';
echo '</div>';

// Template information
if (!empty($submission['template'])) {
    echo '<div class="card" style="margin-bottom: 20px;">';
    echo '<div class="card-header">';
    echo '<h3>Template Information</h3>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<table class="table table-borderless">';
    
    $template = $submission['template'];
    echo '<tr><td><strong>Template Name:</strong></td><td>' . s($template['name'] ?? 'Unknown') . '</td></tr>';
    echo '<tr><td><strong>Template ID:</strong></td><td>' . s($template['id'] ?? 'Unknown') . '</td></tr>';
    
    echo '</table>';
    echo '</div>';
    echo '</div>';
}

// Submitters information
if (!empty($submission['submitters'])) {
    echo '<div class="card" style="margin-bottom: 20px;">';
    echo '<div class="card-header">';
    echo '<h3>Submitters</h3>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<table class="table">';
    echo '<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($submission['submitters'] as $submitter) {
        $name = s($submitter['name'] ?? 'Unknown');
        $email = s($submitter['email'] ?? 'Unknown');
        $role = s($submitter['role'] ?? 'Unknown');
        $status = s($submitter['status'] ?? 'Unknown');
        
        $status_class = 'badge badge-secondary';
        if ($status === 'completed') {
            $status_class = 'badge badge-success';
        } elseif ($status === 'pending') {
            $status_class = 'badge badge-warning';
        }
        
        echo '<tr>';
        echo '<td>' . $name . '</td>';
        echo '<td>' . $email . '</td>';
        echo '<td>' . $role . '</td>';
        echo '<td><span class="' . $status_class . '">' . $status . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';

// Navigation
echo '<div style="margin-top: 20px;">';
echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
echo '</div>';

echo $OUTPUT->footer();
