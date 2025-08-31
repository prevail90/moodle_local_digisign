<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Helpers for Docuseal integration (local_digisign).
 *
 * This lib.php prefers the Docuseal PHP SDK (if installed via composer in plugin/vendor),
 * but falls back to robust curl-based HTTP calls using X-Auth-Token (primary) and AuthToken (fallback).
 *
 * NOTE: The plugin settings provide API key and API URL. The default API URL here is set to
 * https://sign.operatortraining.academy per your environment, but the admin-configured value
 * (Site administration → Plugins → Local plugins → Digisign) will be used when present.
 */

/**
 * Get plugin config object.
 *
 * @return object
 */
function local_digisign_get_config() {
    return (object)[
        'api_key' => get_config('local_digisign', 'api_key'),
        // Default to the user's provided API URL, but allow admin override via settings.
        'api_url' => get_config('local_digisign', 'api_url') ?: 'https://sign.operatortraining.academy',
        'store_local_copy' => get_config('local_digisign', 'store_local_copy')
    ];
}

/**
 * Try to load composer autoload from plugin vendor/ if present.
 *
 * Returns true if autoload was found and required.
 *
 * @return bool
 */
function local_digisign_ensure_autoload() {
    static $loaded = null;
    if ($loaded === null) {
        $vendor = __DIR__ . '/vendor/autoload.php';
        if (file_exists($vendor)) {
            require_once($vendor);
            $loaded = true;
        } else {
            $loaded = false;
        }
    }
    return $loaded;
}

/**
 * Keep last fetch debug info accessible (useful for debugging pages).
 */
function local_digisign_set_last_fetch_debug($debug) {
    static $_dbg = null;
    $_dbg = $debug;
}
function local_digisign_get_last_fetch_debug() {
    static $_dbg = null;
    return $_dbg;
}

/**
 * Fetch templates from Docuseal.
 *
 * Tries SDK first (if available), then falls back to HTTP GET /templates.
 * Will try X-Auth-Token header first and AuthToken header second.
 *
 * Returns an array of template records (or empty array on failure).
 *
 * Also stores a debug trace accessible via local_digisign_get_last_fetch_debug().
 *
 * @param int $limit
 * @return array
 */
function local_digisign_fetch_templates($limit = 100) {
    $cfg = local_digisign_get_config();

    $debug = [
        'time' => date('c'),
        'autoload_present' => false,
        'attempts' => []
    ];

    // Try composer autoload + SDK
    $autoloaded = local_digisign_ensure_autoload();
    $debug['autoload_present'] = (bool)$autoloaded;

    if (class_exists('\\Docuseal\\Api')) {
        try {
            $debug['attempts'][] = ['method' => 'sdk', 'note' => 'instantiating SDK'];
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $resp = $client->listTemplates(['limit' => (int)$limit]);
            $debug['attempts'][] = ['method' => 'sdk', 'response_preview' => substr(print_r($resp, true), 0, 4000)];

            // SDK often returns ['data' => [...]]
            if (is_array($resp) && array_key_exists('data', $resp) && is_array($resp['data'])) {
                local_digisign_set_last_fetch_debug($debug);
                return $resp['data'];
            }
            if (is_array($resp)) {
                local_digisign_set_last_fetch_debug($debug);
                return $resp;
            }

            // Unexpected shape -> record and continue to HTTP fallback
            $debug['attempts'][] = ['method' => 'sdk', 'note' => 'unexpected sdk response shape'];
        } catch (Exception $e) {
            $debug['attempts'][] = ['method' => 'sdk', 'error' => $e->getMessage()];
            debugging('local_digisign: Docuseal SDK error while listing templates: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // fall through to HTTP fallback
        }
    } else {
        $debug['attempts'][] = ['method' => 'sdk', 'note' => 'SDK class not present'];
    }

    // HTTP fallback
    $base = rtrim($cfg->api_url, '/');
    $url = $base . '/templates';
    if (!empty($limit)) {
        $url .= '?limit=' . (int)$limit;
    }

    $headerCandidates = [
        'X-Auth-Token' => 'X-Auth-Token: ' . $cfg->api_key,
        'AuthToken' => 'AuthToken: ' . $cfg->api_key
    ];

    foreach ($headerCandidates as $name => $header) {
        $attempt = [
            'method' => 'http',
            'header' => $name,
            'url' => $url
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$header, 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $resp = curl_exec($ch);
        $curlerr = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $attempt['http_code'] = $code;
        $attempt['curl_error'] = $curlerr ? $curlerr : null;
        $attempt['response_snippet'] = is_string($resp) ? substr($resp, 0, 2000) : null;
        $debug['attempts'][] = $attempt;

        if ($resp === false) {
            // try next header candidate
            continue;
        }

        $decoded = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug['attempts'][] = ['method' => 'http', 'note' => 'json decode error', 'json_error' => json_last_error_msg()];
            continue;
        }

        // Known API shapes:
        //  - { "data": [ ... ] }
        //  - [ { template1 }, { template2 } ]
        //  - { "templates": [ ... ] }
        if (is_array($decoded) && array_key_exists('data', $decoded) && is_array($decoded['data'])) {
            local_digisign_set_last_fetch_debug($debug);
            return $decoded['data'];
        }
        if (is_array($decoded)) {
            $first = reset($decoded);
            if (is_array($first) && (isset($first['id']) || isset($first['slug']) || isset($first['name']))) {
                local_digisign_set_last_fetch_debug($debug);
                return $decoded;
            }
            if (array_key_exists('templates', $decoded) && is_array($decoded['templates'])) {
                local_digisign_set_last_fetch_debug($debug);
                return $decoded['templates'];
            }
        }
    }

    // Nothing returned
    local_digisign_set_last_fetch_debug($debug);
    debugging('local_digisign: failed to fetch templates. Attempts: ' . substr(print_r($debug, true), 0, 4000), DEBUG_DEVELOPER);
    return [];
}

/**
 * Create a submission for a template for a user.
 *
 * Returns decoded API response (array) or null.
 *
 * @param int $templateid
 * @param string $useremail
 * @return array|null
 */
function local_digisign_create_submission($templateid, $useremail) {
    $cfg = local_digisign_get_config();

    // Try SDK first
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $payload = [
                'template_id' => (int)$templateid,
                'send_email' => false,
                'submitters' => [
                    ['role' => 'Signer', 'email' => $useremail]
                ]
            ];
            $resp = $client->createSubmission($payload);
            if (is_array($resp)) {
                return $resp;
            }
        } catch (Exception $e) {
            debugging('local_digisign: Docuseal SDK create submission error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // HTTP fallback: POST to /submissions with X-Auth-Token header
    $url = rtrim($cfg->api_url, '/') . '/submissions';
    $payload = json_encode([
        'template_id' => (int)$templateid,
        'send_email' => false,
        'submitters' => [
            ['role' => 'Signer', 'email' => $useremail]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Auth-Token: ' . $cfg->api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resp = curl_exec($ch);
    $curlerr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        debugging('local_digisign: curl error create submission: ' . $curlerr, DEBUG_DEVELOPER);
        return null;
    }

    if ($code >= 200 && $code < 300 && $resp) {
        $decoded = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        // if not JSON, just return raw (unlikely)
        return ['raw' => $resp];
    }

    debugging('local_digisign: create_submission failed code=' . intval($code) . ' resp=' . substr($resp ?: '', 0, 1000), DEBUG_DEVELOPER);
    return null;
}

/**
 * Download signed PDF for a submission id.
 *
 * Returns raw bytes (string) or null.
 *
 * @param string|int $submissionid
 * @return string|null
 */
function local_digisign_download_signed_pdf($submissionid) {
    $cfg = local_digisign_get_config();

    // Try SDK if available
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            if (method_exists($client, 'getSubmissionDocuments')) {
                $docs = $client->getSubmissionDocuments((int)$submissionid);
                if (is_array($docs) && !empty($docs)) {
                    $items = is_array($docs['data'] ?? null) ? $docs['data'] : (is_array($docs) ? $docs : []);
                    if (!empty($items) && is_array($items[0]) && !empty($items[0]['url'])) {
                        $url = $items[0]['url'];
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Auth-Token: ' . $cfg->api_key]);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        $pdf = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        if ($code >= 200 && $code < 300 && $pdf !== false) {
                            return $pdf;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            debugging('local_digisign: SDK getSubmissionDocuments error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // Fallback: download endpoint /submissions/{id}/download with X-Auth-Token
    $url = rtrim($cfg->api_url, '/') . '/submissions/' . urlencode($submissionid) . '/download';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Auth-Token: ' . $cfg->api_key]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resp = curl_exec($ch);
    $curlerr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        debugging('local_digisign: curl error download signed pdf: ' . $curlerr, DEBUG_DEVELOPER);
        return null;
    }

    if ($code >= 200 && $code < 300 && $resp !== false) {
        return $resp;
    }

    debugging('local_digisign: download_signed_pdf failed code=' . intval($code), DEBUG_DEVELOPER);
    return null;
}

/**
 * Save file bytes into user's private file area.
 *
 * Returns stored file_record (stored_file instance) or false on error.
 *
 * @param int $userid
 * @param string $filename
 * @param string $contents
 * @return stored_file|false
 */
function local_digisign_save_signed_file_for_user($userid, $filename, $contents) {
    $fs = get_file_storage();
    $context = context_user::instance($userid);
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'user',
        'filearea'  => 'private',
        'itemid'    => 0,
        'filepath'  => '/digisign/',
        'filename'  => $filename
    ];
    return $fs->create_file_from_string($filerecord, $contents);
}

/**
 * Record a submission in local DB table local_digisign_sub.
 *
 * @param int $userid
 * @param int $templateid
 * @param string $templateslug
 * @param string $submissionid
 * @param string $submitterslug
 * @param string $status
 * @return int inserted record id
 */
function local_digisign_record_submission($userid, $templateid, $templateslug, $submissionid, $submitterslug, $status='created') {
    global $DB;
    $now = time();
    $record = new stdClass();
    $record->userid = $userid;
    $record->templateid = $templateid ?: 0;
    $record->templateslug = $templateslug ?: '';
    $record->submissionid = $submissionid ?: '';
    $record->submitterslug = $submitterslug ?: '';
    $record->status = $status;
    $record->timecreated = $now;
    $record->timemodified = $now;
    return $DB->insert_record('local_digisign_sub', $record);
}

/**
 * Mark submission complete by submissionid (updates local record status).
 *
 * @param string $submissionid
 * @return bool
 */
function local_digisign_mark_complete($submissionid) {
    global $DB;
    $rec = $DB->get_record('local_digisign_sub', ['submissionid' => (string)$submissionid]);
    if ($rec) {
        $rec->status = 'completed';
        $rec->timemodified = time();
        $DB->update_record('local_digisign_sub', $rec);
        return true;
    }
    return false;
}