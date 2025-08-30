<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/digisign/lib.php');

require_login();

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

header('Content-Type: application/json');

// Basic CSRF check for mutating actions.
if (in_array($action, ['create_submission', 'complete_submission'])) {
    require_sesskey();
}

if ($action === 'create_submission') {
    $templateid = required_param('templateid', PARAM_INT);
    $res = local_digisign_create_submission($templateid, $USER->email);
    if ($res) {
        // Map likely fields; adjust according to Docuseal response.
        $submissionid = $res['id'] ?? ($res['submission']['id'] ?? '');
        $submitterslug = '';
        if (!empty($res['submitters'][0]['slug'])) {
            $submitterslug = $res['submitters'][0]['slug'];
        } else if (!empty($res['submitters'][0]['uuid'])) {
            $submitterslug = $res['submitters'][0]['uuid'];
        } else if (!empty($res['submitters'][0]['id'])) {
            $submitterslug = $res['submitters'][0]['id'];
        }

        local_digisign_record_submission($USER->id, $templateid, ($res['template']['slug'] ?? ''), $submissionid, $submitterslug, 'created');

        echo json_encode(['success' => true, 'submission_id' => $submissionid, 'submitter_slug' => $submitterslug]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'failed_api']);
    exit;
}

if ($action === 'complete_submission') {
    $submissionid = required_param('submission_id', PARAM_RAW);
    $pdf = local_digisign_download_signed_pdf($submissionid);
    if ($pdf !== null) {
        $filename = 'digisign-' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $submissionid) . '.pdf';
        $file = local_digisign_save_signed_file_for_user($USER->id, $filename, $pdf);
        local_digisign_mark_complete($submissionid);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'download_failed']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown_action']);
exit;