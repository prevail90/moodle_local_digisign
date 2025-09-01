<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/index.php'));
$PAGE->set_title('OTA Digisign');
$PAGE->set_heading('OTA Digisign');

// Initialize AMD module (calls the module's init())
$PAGE->requires->js_call_amd('local_digisign/digisign', 'init');

$limit = 100;
$templates = local_digisign_fetch_templates($limit);

// Get submission statuses for all templates
$template_statuses = local_digisign_get_all_template_statuses($templates, $USER->id, $USER->email);

// Log debug information to Moodle logs
local_digisign_log('index page - templates count', count($templates));
local_digisign_log('index page - template statuses', $template_statuses);

echo $OUTPUT->header();
echo $OUTPUT->heading('OTA Digisign');
echo '<p style="text-align: center; color: #666; margin-bottom: 20px;">Select a form to sign.</p>';

// Add navigation links
echo '<div style="margin-bottom: 20px;">';
echo '<a href="' . new moodle_url('/local/digisign/submissions.php') . '" class="btn btn-secondary">View Submissions</a>';
echo '</div>';

if (empty($templates)) {
    echo html_writer::div(get_string('notemplatesfound', 'local_digisign'), 'local-digisign-no-templates');
    echo $OUTPUT->footer();
    exit;
}

// Add container for JavaScript
echo '<div id="local-digisign-root" data-useremail="' . s($USER->email) . '">';

// Add CSS for status indicators
echo '<style>
.digisign-tile {
    position: relative;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    margin: 10px;
    display: inline-block;
    width: 250px;
    vertical-align: top;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.digisign-tile:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.status-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.status-none {
    background: #6c757d;
}

.status-created {
    background: #fd7e14;
}

.status-in_progress {
    background: #ffc107;
    color: #212529;
}

.status-completed {
    background: #28a745;
}

.status-indicator i {
    font-size: 14px;
}

.tile-preview {
    text-align: center;
    margin-bottom: 10px;
}

.tile-preview img {
    max-width: 100%;
    max-height: 120px;
    border-radius: 4px;
}

.tile-preview.empty {
    width: 100%;
    height: 120px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    border-radius: 4px;
}

.tile-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.tile-status {
    font-size: 12px;
    margin-bottom: 10px;
    padding: 4px 8px;
    border-radius: 4px;
    text-align: center;
    font-weight: 500;
}

.status-text-none {
    background: #f8f9fa;
    color: #6c757d;
}

.status-text-created {
    background: #fff3cd;
    color: #856404;
}

.status-text-in_progress {
    background: #fff3cd;
    color: #856404;
}

.status-text-completed {
    background: #d4edda;
    color: #155724;
}

.start-btn {
    width: 100%;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #007bff;
    color: white;
    cursor: pointer;
    transition: background 0.3s ease;
}

.start-btn:hover {
    background: #0056b3;
}

.start-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.submitter-info {
    font-size: 11px;
    color: #666;
    margin-top: 5px;
    padding: 4px;
    background: #f8f9fa;
    border-radius: 3px;
}

.local-digisign-tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: flex-start;
}
</style>';

// If not empty, render tiles with status indicators
echo '<div class="local-digisign-tiles">';

foreach ($templates as $t) {
    $tid = isset($t['id']) ? s($t['id']) : '';
    $title = isset($t['name']) ? s($t['name']) : (isset($t['title']) ? s($t['title']) : get_string('untitled', 'local_digisign'));
    $slug = isset($t['slug']) ? s($t['slug']) : '';

    // Get status for this template
    $status_info = isset($template_statuses[$tid]) ? $template_statuses[$tid] : ['status' => 'none'];
    $status = $status_info['status'];
    $submitters = isset($status_info['submitters']) ? $status_info['submitters'] : [];

    $preview = '';
    if (!empty($t['documents']) && is_array($t['documents'])) {
        $doc0 = $t['documents'][0];
        if (!empty($doc0['preview_image_url'])) {
            $preview = s($doc0['preview_image_url']);
        } else if (!empty($doc0['preview_image'])) {
            $preview = s($doc0['preview_image']);
        } else if (!empty($doc0['url'])) {
            $preview = s($doc0['url']);
        }
    }

    // Tile markup with status indicator
    echo '<div class="digisign-tile" data-templateid="'. $tid .'" data-template-slug="'. $slug .'">';
    
    // Status indicator icon
    echo '<div class="status-indicator status-' . $status . '">';
    if ($status === 'completed') {
        echo '<i class="fa fa-check"></i>';
    } elseif ($status === 'created') {
        echo '<i class="fa fa-clock-o"></i>';
    } elseif ($status === 'in_progress') {
        echo '<i class="fa fa-spinner"></i>';
    } else {
        echo '<i class="fas fa-circle"></i>';
    }
    echo '</div>';
    
    // Preview image
    if ($preview !== '') {
        echo '<div class="tile-preview"><img src="'. $preview .'" alt="'. $title .'"></div>';
    } else {
        echo '<div class="tile-preview empty">';
        echo get_string('nopreview', 'local_digisign');
        echo '</div>';
    }
    
    // Title
    echo '<div class="tile-title">' . $title . '</div>';
    
    // Status text
    $status_text = '';
    switch ($status) {
        case 'none':
            $status_text = get_string('status_not_started', 'local_digisign');
            break;
        case 'created':
            $status_text = get_string('status_created', 'local_digisign');
            break;
        case 'in_progress':
            $status_text = get_string('status_in_progress', 'local_digisign');
            break;
        case 'completed':
            $status_text = get_string('status_completed', 'local_digisign');
            break;
        default:
            $status_text = get_string('status_unknown', 'local_digisign');
    }
    
    echo '<div class="tile-status status-text-' . $status . '">' . $status_text . '</div>';
    
    // Submitter information (if any)
    if (!empty($submitters)) {
        echo '<div class="submitter-info">';
        $submitter_count = count($submitters);
        $completed_count = 0;
        foreach ($submitters as $submitter) {
            if ($submitter['status'] === 'completed') {
                $completed_count++;
            }
        }
        echo get_string('submitters_info', 'local_digisign', ['completed' => $completed_count, 'total' => $submitter_count]);
        echo '</div>';
    }
    
    // Action button
    if ($status === 'completed') {
        echo '<button class="start-btn" disabled>' . get_string('completed', 'local_digisign') . '</button>';
    } elseif ($status === 'none') {
        // No submission exists - create new
        echo '<button class="start-btn create-submission" type="button" data-templateid="' . $tid . '">' . get_string('start', 'local_digisign') . '</button>';
    } else {
        // Submission exists but not completed - open existing
        echo '<button class="start-btn open-submission" type="button" data-submissionid="' . $status_info['submission_id'] . '">' . get_string('continue', 'local_digisign') . '</button>';
    }
    
    echo '</div>';
}

echo '</div>'; // tiles
echo '</div>'; // local-digisign-root

// Load the JavaScript module
$PAGE->requires->js_call_amd('local_digisign/digisign', 'init');

echo $OUTPUT->footer();