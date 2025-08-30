<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/digisign/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_digisign'));
$PAGE->set_heading(get_string('pluginname', 'local_digisign'));

$PAGE->requires->js('/local/digisign/amd/src/digisign.js'); // dev path; consider amd build in production

$templates = local_digisign_fetch_templates();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choose_template', 'local_digisign'));

echo '<div id="local-digisign-root" data-useremail="'.s($USER->email).'">';
echo '<div class="local-digisign-tiles">';
foreach ($templates as $t) {
    $tid = s($t['id'] ?? $t['template_id'] ?? 0);
    $title = s($t['name'] ?? $t['title'] ?? 'Unnamed');
    $slug = s($t['slug'] ?? '');
    // completed check
    global $DB;
    $rec = $DB->get_record('local_digisign_sub', ['userid' => $USER->id, 'templateslug' => $slug, 'status' => 'completed']);
    $completed = (bool)$rec;

    echo '<div class="digisign-tile" data-templateid="'. $tid .'" data-templateslug="'. $slug .'">';
    echo '<div class="title">'. $title .'</div>';
    if ($completed) {
        echo '<div class="status completed">✔ '.get_string('completed', 'local_digisign').'</div>';
    } else {
        echo '<button class="start-btn">'.get_string('start', 'local_digisign').'</button>';
    }
    echo '</div>';
}
echo '</div>'; // tiles
// Modal container for embed
echo '<div id="local-digisign-modal" style="display:none; position:fixed; left:2%; top:5%; width:96%; height:90%; background:#fff; z-index:99999; overflow:auto;">';
echo '<div style="padding:8px;"><button id="local-digisign-close">'.get_string('close', 'local_digisign').'</button></div>';
echo '<div id="local-digisign-widget" style="height:calc(100% - 48px);"></div>';
echo '</div>';
echo '</div>'; // root

echo $OUTPUT->footer();