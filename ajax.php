<?php
// AJAX endpoints for local_digisign plugin.
// Supported actions:
//  - create_submission (POST): creates a Docuseal submission for the current user and records it locally.
//  - complete_submission (POST): downloads signed PDF for a submission, optionally stores it in user's private files,
//                               and marks the submission completed.
//
// Responses are JSON:
//  { success: true, submission_id: ..., submitter_slug: '...', message: '...' }
//  { success: false, error: 'message' }

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

global $DB, $USER, $CFG;

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Basic JSON responder
function respond_json($data) {
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Ensure sesskey for state-changing actions.
if ($action === 'create_submission' || $action === 'complete_submission') {
    require_sesskey();
}

if ($action === 'create_submission') {
    $templateid = required_param('templateid', PARAM_INT);
    $useremail = $USER->email;

    // Try to create submission via helper. This will prefer SDK if vendor/autoload.php is present.
    $resp = local_digisign_create_submission($templateid, $useremail);

    if (!$resp || !is_array($resp)) {
        respond_json(['success' => false, 'error' => get_string('failed_create_submission', 'local_digisign')]);
    }

    // Extract submission id and submitter slug from various possible shapes returned by SDK or HTTP.
    $submissionid = null;
    $submitterslug = null;
    $templateslug = '';

    // Common keys to inspect.
    if (!empty($resp['id'])) {
        $submissionid = (string)$resp['id'];
    }
    if (empty($submissionid) && !empty($resp['submission_id'])) {
        $submissionid = (string)$resp['submission_id'];
    }
    if (empty($submissionid) && !empty($resp['data']['id'])) {
        $submissionid = (string)$resp['data']['id'];
    }
    // Submitter slug: SDK sometimes returns submitter_slug top-level or inside submitters array.
    if (!empty($resp['submitter_slug'])) {
        $submitterslug = (string)$resp['submitter_slug'];
    } else if (!empty($resp['submitters']) && is_array($resp['submitters'])) {
        // try first submitter slug or submitter->slug
        $s0 = reset($resp['submitters']);
        if (!empty($s0['slug'])) {
            $submitterslug = (string)$s0['slug'];
        } else if (!empty($s0['submitter_slug'])) {
            $submitterslug = (string)$s0['submitter_slug'];
        }
    } else if (!empty($resp['data']['submitters']) && is_array($resp['data']['submitters'])) {
        $s0 = reset($resp['data']['submitters']);
        if (!empty($s0['slug'])) {
            $submitterslug = (string)$s0['slug'];
        } else if (!empty($s0['submitter_slug'])) {
            $submitterslug = (string)$s0['submitter_slug'];
        }
    } else if (!empty($resp['submitter']) && is_array($resp['submitter']) && !empty($resp['submitter']['slug'])) {
        $submitterslug = (string)$resp['submitter']['slug'];
    }

    // Try to get template slug if returned
    if (!empty($resp['template_slug'])) {
        $templateslug = (string)$resp['template_slug'];
    } else if (!empty($resp['data']['template']['slug'])) {
        $templateslug = (string)$resp['data']['template']['slug'];
    } else {
        // template slug might not be returned; leave empty
        $templateslug = '';
    }

    // If we couldn't find a submission id but we have a submitter slug, use that as a fallback "submitter" identifier.
    if (empty($submissionid) && !empty($submitterslug)) {
        $submissionid = $submitterslug;
    }

    // Record in DB so we can track status and associate with user.
    try {
        $recordid = local_digisign_record_submission($USER->id, $templateid, $templateslug, $submissionid, $submitterslug, 'created');
    } catch (Exception $e) {
        debugging('local_digisign: failed to record submission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // proceed anyway; returning response to client
        $recordid = false;
    }

    respond_json([
        'success' => true,
        'submission_id' => $submissionid,
        'submitter_slug' => $submitterslug,
        'recordid' => $recordid,
    ]);
}

if ($action === 'complete_submission') {
    $submissionid = required_param('submission_id', PARAM_RAW); // can be string slug or numeric id

    // Find local record and ensure it belongs to current user
    $localrec = $DB->get_record('local_digisign_sub', ['submissionid' => (string)$submissionid], '*', IGNORE_MISSING);
    if (!$localrec) {
        respond_json(['success' => false, 'error' => 'Submission not found']);
    }
    if ((int)$localrec->userid !== $USER->id) {
        respond_json(['success' => false, 'error' => 'Permission denied']);
    }

    // Attempt to download signed PDF bytes
    $pdfbytes = local_digisign_download_signed_pdf($submissionid);

    $cfg = local_digisign_get_config();

    $savedfileinfo = null;
    if ($pdfbytes !== null && $pdfbytes !== false) {
        if (!empty($cfg->store_local_copy)) {
            // create filename using template id or submission id and timestamp
            $filename = 'digisign_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$submissionid) . '_' . date('Ymd_His') . '.pdf';
            $filerec = local_digisign_save_signed_file_for_user($USER->id, $filename, $pdfbytes);
            if ($filerec) {
                $savedfileinfo = [
                    'filename' => $filerec->get_filename(),
                    'filepath' => $filerec->get_filepath(),
                    'contextid' => $filerec->get_contextid()
                ];
            } else {
                // failed to save file
                respond_json(['success' => false, 'error' => get_string('failed_store_file', 'local_digisign')]);
            }
        } else {
            // store_local_copy disabled; we still consider it success
            $savedfileinfo = null;
        }
    } else {
        // No PDF bytes found yet — this may happen if Docuseal hasn't processed the signed document yet.
        respond_json(['success' => false, 'error' => 'Signed file not available yet']);
    }

    // Mark submission complete in local DB
    $marked = local_digisign_mark_complete($submissionid);

    respond_json([
        'success' => true,
        'message' => 'Submission completed',
        'savedfile' => $savedfileinfo,
        'marked' => $marked
    ]);
}

// Unknown action
respond_json(['success' => false, 'error' => 'Invalid action']);