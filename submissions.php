<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/submissions.php'));
$PAGE->set_title('OTA Digisign - Submissions');
$PAGE->set_heading('OTA Digisign - Submissions');

// Get filter parameters
$status = optional_param('status', '', PARAM_ALPHANUM);
$template_id = optional_param('template_id', 0, PARAM_INT);
$limit = optional_param('limit', 50, PARAM_INT);

// Build filters array
$filters = [];
if (!empty($status)) {
    $filters['status'] = $status;
}
if (!empty($template_id)) {
    $filters['template_id'] = $template_id;
}

// Fetch submissions for the current user only
$useremail = $USER->email;
$submissions = local_digisign_fetch_submissions($limit, $filters, $useremail);

// Get fetch debug info recorded by lib.php
$fetchdebug = local_digisign_get_last_fetch_debug();

echo $OUTPUT->header();
echo $OUTPUT->heading('OTA Digisign - Submissions');
echo '<p style="text-align: center; color: #666; margin-bottom: 20px;">Submissions for ' . fullname($USER) . '</p>';

// Filter form
echo '<div class="local-digisign-filters" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
echo '<form method="get" action="">';
echo '<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">';

echo '<div>';
echo '<label for="status">Status: </label>';
echo '<select name="status" id="status">';
echo '<option value="">All Statuses</option>';
echo '<option value="completed"' . ($status === 'completed' ? ' selected' : '') . '>Completed</option>';
echo '<option value="pending"' . ($status === 'pending' ? ' selected' : '') . '>Pending</option>';
echo '<option value="draft"' . ($status === 'draft' ? ' selected' : '') . '>Draft</option>';
echo '</select>';
echo '</div>';

echo '<div>';
echo '<label for="limit">Limit: </label>';
echo '<select name="limit" id="limit">';
echo '<option value="10"' . ($limit === 10 ? ' selected' : '') . '>10</option>';
echo '<option value="25"' . ($limit === 25 ? ' selected' : '') . '>25</option>';
echo '<option value="50"' . ($limit === 50 ? ' selected' : '') . '>50</option>';
echo '<option value="100"' . ($limit === 100 ? ' selected' : '') . '>100</option>';
echo '</select>';
echo '</div>';

echo '<div>';
echo '<button type="submit" class="btn btn-primary">Filter</button>';
echo '<a href="' . $PAGE->url . '" class="btn btn-secondary" style="margin-left: 10px;">Clear</a>';
echo '</div>';

echo '</div>';
echo '</form>';
echo '</div>';

// Log debug information to Moodle logs
local_digisign_log('submissions page - raw submissions array', $submissions);
local_digisign_log('submissions page - fetch attempts trace', $fetchdebug);

if (empty($submissions)) {
    echo html_writer::div(get_string('nosubmissionsfound', 'local_digisign'), 'local-digisign-no-submissions');
    echo $OUTPUT->footer();
    exit;
}

// Display submissions table
echo '<div class="local-digisign-submissions">';
echo '<table class="table table-striped table-hover">';
echo '<thead>';
echo '<tr>';
echo '<th>ID</th>';
echo '<th>Status</th>';
echo '<th>Template</th>';
echo '<th>Created</th>';
echo '<th>Updated</th>';
echo '<th>Actions</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($submissions as $submission) {
    $id = isset($submission['id']) ? s($submission['id']) : '';
    $status = isset($submission['status']) ? s($submission['status']) : '';
    $template_name = '';
    $created_at = '';
    $updated_at = '';
    
    // Extract template name
    if (!empty($submission['template']) && is_array($submission['template'])) {
        $template_name = isset($submission['template']['name']) ? s($submission['template']['name']) : 
                        (isset($submission['template']['title']) ? s($submission['template']['title']) : 'Unknown Template');
    }
    
    // Format dates
    if (!empty($submission['created_at'])) {
        $created_at = date('Y-m-d H:i:s', strtotime($submission['created_at']));
    }
    if (!empty($submission['updated_at'])) {
        $updated_at = date('Y-m-d H:i:s', strtotime($submission['updated_at']));
    }
    
    // Status badge
    $status_class = 'badge badge-secondary';
    if ($status === 'completed') {
        $status_class = 'badge badge-success';
    } elseif ($status === 'pending') {
        $status_class = 'badge badge-warning';
    } elseif ($status === 'draft') {
        $status_class = 'badge badge-info';
    }
    
    echo '<tr>';
    echo '<td>' . $id . '</td>';
    echo '<td><span class="' . $status_class . '">' . $status . '</span></td>';
    echo '<td>' . $template_name . '</td>';
    echo '<td>' . $created_at . '</td>';
    echo '<td>' . $updated_at . '</td>';
    echo '<td>';
    echo '<a href="' . new moodle_url('/local/digisign/view_submission.php', ['id' => $id]) . '" class="btn btn-sm btn-primary">View</a>';
    if ($status === 'completed') {
        echo '<a href="' . new moodle_url('/local/digisign/download.php', ['id' => $id]) . '" class="btn btn-sm btn-success" style="margin-left: 5px;">Download</a>';
    } elseif ($status === 'pending' || $status === 'draft') {
        // Check if we can still access this submission
        $can_access = false;
        if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
            foreach ($submission['submitters'] as $submitter) {
                if (isset($submitter['email']) && $submitter['email'] === $USER->email && 
                    isset($submitter['role']) && strtolower($submitter['role']) === 'operator') {
                    if (!empty($submitter['slug'])) {
                        $can_access = true;
                        break;
                    }
                }
            }
        }
        
        if ($can_access) {
            echo '<a href="' . new moodle_url('/local/digisign/sign.php', ['action' => 'edit', 'id' => $id]) . '" class="btn btn-sm btn-warning" style="margin-left: 5px;">Continue</a>';
        } else {
            echo '<span class="badge badge-danger" style="margin-left: 5px;">Expired</span>';
        }
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

// Navigation links
echo '<div style="margin-top: 20px;">';
echo '<a href="' . new moodle_url('/local/digisign/index.php') . '" class="btn btn-secondary">← Back to Templates</a>';
echo '</div>';

echo $OUTPUT->footer();
