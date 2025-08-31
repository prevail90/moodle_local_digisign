<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_digisign'));
$PAGE->set_heading(get_string('pluginname', 'local_digisign'));

// Initialize AMD module (calls the module's init())
$PAGE->requires->js_call_amd('local_digisign/digisign', 'init');

$limit = 100;
$templates = local_digisign_fetch_templates($limit);

// Get fetch debug info recorded by lib.php
$fetchdebug = local_digisign_get_last_fetch_debug();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choose_template', 'local_digisign'));

// Debug output: show the raw templates JSON and fetch debug trace
echo html_writer::tag('h3', 'Debug: raw templates array');
echo html_writer::tag('pre', s(print_r($templates, true)));

echo html_writer::tag('h3', 'Debug: fetch attempts trace (local_digisign_get_last_fetch_debug)');
echo html_writer::tag('pre', s(print_r($fetchdebug, true)));

if (empty($templates)) {
    echo html_writer::div(get_string('notemplatesfound', 'local_digisign'), 'local-digisign-no-templates');
    echo $OUTPUT->footer();
    exit;
}

// If not empty, render simple list (same as normal flow)
echo '<div class="local-digisign-tiles">';

foreach ($templates as $t) {
    $tid = isset($t['id']) ? s($t['id']) : '';
    $title = isset($t['name']) ? s($t['name']) : (isset($t['title']) ? s($t['title']) : get_string('untitled', 'local_digisign'));
    $slug = isset($t['slug']) ? s($t['slug']) : '';

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

    // Completed check (by templateslug)
    $completed = false;
    if ($slug !== '') {
        global $DB;
        $rec = $DB->get_record('local_digisign_sub', ['userid' => $USER->id, 'templateslug' => $slug, 'status' => 'completed']);
        $completed = (bool)$rec;
    }

    // Tile markup
    echo '<div class="digisign-tile" data-templateid="'. $tid .'" data-templateslug="'. $slug .'">';
    if ($preview !== '') {
        echo '<div class="tile-preview"><img src="'. $preview .'" alt="'. $title .'" style="max-width:200px; max-height:120px;"></div>';
    } else {
        echo '<div class="tile-preview empty" style="width:200px;height:120px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#888;">';
        echo get_string('nopreview', 'local_digisign');
        echo '</div>';
    }
    echo '<div class="tile-title">' . $title . '</div>';
    if ($completed) {
        echo '<div class="status completed" style="color:green;font-weight:bold;">✔ '.get_string('completed', 'local_digisign').'</div>';
    } else {
        echo '<button class="start-btn btn btn-primary" type="button">'.get_string('start', 'local_digisign').'</button>';
    }
    echo '</div>';
}

echo '</div>'; // tiles

// Modal container for embed
echo '<div id="local-digisign-modal" style="display:none; position:fixed; left:2%; top:5%; width:96%; height:90%; background:#fff; z-index:99999; overflow:auto; box-shadow:0 4px 12px rgba(0,0,0,0.2);">';
echo '<div style="padding:8px;background:#f8f8f8;display:flex;justify-content:space-between;align-items:center;">';
echo '<div style="font-weight:600;">' . get_string('pluginname', 'local_digisign') . '</div>';
echo '<div><button id="local-digisign-close" class="btn btn-secondary">'.get_string('close', 'local_digisign').'</button></div>';
echo '</div>';
echo '<div id="local-digisign-widget" style="height:calc(100% - 48px);"></div>';
echo '</div>';

echo $OUTPUT->footer();