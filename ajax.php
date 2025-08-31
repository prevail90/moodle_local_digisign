<?php
// AJAX endpoints for local_digisign plugin.
// Supported actions:
//  - create_submission (POST): creates a Docuseal submission for the current user and records it locally.
//  - complete_submission (POST): downloads signed PDF for a submission, optionally stores it in user's private files,
//                               and marks the submission completed.
//  - fetch_submissions (GET): fetches submissions from Docuseal API with optional filters.
//  - get_submission (GET): gets details for a specific submission.
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
    $username = fullname($USER); // Get user's full name

    // Log the submission creation attempt
    local_digisign_log('create_submission - user: ' . $username . ' (' . $useremail . '), template: ' . $templateid);

    // Try to create submission via helper. This will prefer SDK if vendor/autoload.php is present.
    $resp = local_digisign_create_submission($templateid, $useremail, $username);

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

    // Log successful submission creation
    local_digisign_log('create_submission success - submission_id: ' . $submissionid . ', submitter_slug: ' . $submitterslug);

    respond_json([
        'success' => true,
        'submission_id' => $submissionid,
        'submitter_slug' => $submitterslug,
        'recordid' => $recordid,
    ]);
}

if ($action === 'complete_submission') {
    $submissionid = required_param('submission_id', PARAM_RAW); // can be string slug or numeric id

    // Log the submission completion attempt
    local_digisign_log('complete_submission - user: ' . fullname($USER) . ' (' . $USER->email . '), submission: ' . $submissionid);

    // Find local record and ensure it belongs to current user
    $localrec = $DB->get_record('local_digisign_sub', ['submissionid' => (string)$submissionid], '*', IGNORE_MISSING);
    if (!$localrec) {
        respond_json(['success' => false, 'error' => 'Submission not found']);
    }
    if ((int)$localrec->userid !== $USER->id) {
        respond_json(['success' => false, 'error' => 'Permission denied']);
    }

    // Attempt to download signed PDF bytes (for verification that it's available)
    $pdfbytes = local_digisign_download_signed_pdf($submissionid);

    if ($pdfbytes === null || $pdfbytes === false) {
        // No PDF bytes found yet — this may happen if Docuseal hasn't processed the signed document yet.
        respond_json(['success' => false, 'error' => 'Signed file not available yet']);
    }

    // Mark submission complete in local DB
    $marked = local_digisign_mark_complete($submissionid);

    respond_json([
        'success' => true,
        'message' => 'Submission completed',
        'marked' => $marked
    ]);
}

if ($action === 'fetch_submissions') {
    $limit = optional_param('limit', 100, PARAM_INT);
    $status = optional_param('status', '', PARAM_ALPHANUM);
    $template_id = optional_param('template_id', 0, PARAM_INT);
    $useremail = $USER->email; // Get current user's email
    
    // Build filters array
    $filters = [];
    if (!empty($status)) {
        $filters['status'] = $status;
    }
    if (!empty($template_id)) {
        $filters['template_id'] = $template_id;
    }
    
    // Fetch submissions from DocuSeal API, filtered by user email for operator role
    $submissions = local_digisign_fetch_submissions($limit, $filters, $useremail);
    
    if ($submissions === false) {
        respond_json(['success' => false, 'error' => 'Failed to fetch submissions']);
    }
    
    respond_json([
        'success' => true,
        'submissions' => $submissions,
        'count' => count($submissions)
    ]);
}

if ($action === 'get_submission') {
    $submissionid = required_param('submission_id', PARAM_RAW);
    
    // Find local record and ensure it belongs to current user
    $localrec = $DB->get_record('local_digisign_sub', ['submissionid' => (string)$submissionid], '*', IGNORE_MISSING);
    if (!$localrec) {
        respond_json(['success' => false, 'error' => 'Submission not found']);
    }
    if ((int)$localrec->userid !== $USER->id) {
        respond_json(['success' => false, 'error' => 'Permission denied']);
    }
    
    // Get submission details from DocuSeal API
    $submission = local_digisign_get_submission($submissionid);
    
    if (!$submission) {
        respond_json(['success' => false, 'error' => 'Failed to fetch submission details']);
    }
    
    // Extract submitter slug for embed URL
    $submitterslug = null;
    if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
        $s0 = reset($submission['submitters']);
        if (!empty($s0['slug'])) {
            $submitterslug = (string)$s0['slug'];
        } else if (!empty($s0['submitter_slug'])) {
            $submitterslug = (string)$s0['submitter_slug'];
        }
    }
    
    respond_json([
        'success' => true,
        'submission_id' => $submissionid,
        'submitter_slug' => $submitterslug,
        'submission_data' => $submission
    ]);
}

// Unknown action
respond_json(['success' => false, 'error' => 'Invalid action']);