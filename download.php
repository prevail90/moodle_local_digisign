<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);

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

// Check if submission is completed
if ($submission['status'] !== 'completed') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('submission_not_completed', 'local_digisign'), 'error');
    echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
    echo $OUTPUT->footer();
    exit;
}

// Download the signed PDF
$pdfbytes = local_digisign_download_signed_pdf($submission_id);

if ($pdfbytes === null || $pdfbytes === false) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('submission_download_failed', 'local_digisign'), 'error');
    echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">← Back to Submissions</a>';
    echo $OUTPUT->footer();
    exit;
}

// Get template name for filename
$template_name = 'submission';
if (!empty($submission['template']) && is_array($submission['template'])) {
    $template_name = isset($submission['template']['name']) ? $submission['template']['name'] : 'submission';
}

// Clean filename
$template_name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $template_name);
$template_name = str_replace(' ', '_', $template_name);
$filename = $template_name . '_' . $submission_id . '.pdf';

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfbytes));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output PDF
echo $pdfbytes;
exit;
