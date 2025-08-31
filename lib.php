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
        'api_url' => get_config('local_digisign', 'api_url') ?: 'https://sign.operatortraining.academy/api',
        'timeout' => get_config('local_digisign', 'timeout') ?: 30
    ];
}

/**
 * Ensure autoloader is available for Docuseal SDK.
 *
 * @return bool
 */
function local_digisign_ensure_autoload() {
    // Check if composer autoloader exists
    $composer_autoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        require_once($composer_autoload);
        return true;
    }
    
    // Check if SDK is manually installed in plugin
    $plugin_autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($plugin_autoload)) {
        require_once($plugin_autoload);
        return true;
    }
    
    // Check if SDK class is already available
    if (class_exists('\\Docuseal\\Api')) {
        return true;
    }
    
    return false;
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
 * Log debug information for local_digisign plugin.
 *
 * @param string $message
 * @param mixed $data Optional data to log
 * @param int $level Debug level (default: DEBUG_DEVELOPER)
 */
function local_digisign_log($message, $data = null, $level = DEBUG_DEVELOPER) {
    if (debugging()) {
        $log_message = 'local_digisign: ' . $message;
        if ($data !== null) {
            $log_message .= ' - ' . print_r($data, true);
        }
        debugging($log_message, $level);
    }
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $cfg->timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $cfg->timeout);
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
function local_digisign_create_submission($templateid, $useremail, $username = '') {
    $cfg = local_digisign_get_config();

    // Try SDK first
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $payload = [
                'template_id' => (int)$templateid,
                'send_email' => false,
                'submitters' => [
                    [
                        'role' => 'Operator',
                        'email' => $useremail,
                        'name' => $username
                    ]
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
            [
                'role' => 'Operator',
                'email' => $useremail,
                'name' => $username
            ]
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
 * Get local submission record from database.
 *
 * @param string $submissionid
 * @param int $userid
 * @return stdClass|null
 */
function local_digisign_get_local_submission($submissionid, $userid) {
    global $DB;
    
    $record = $DB->get_record('local_digisign_sub', [
        'submissionid' => $submissionid,
        'userid' => $userid
    ]);
    
    return $record ?: null;
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

/**
 * Fetch submissions from Docuseal.
 *
 * Tries SDK first (if available), then falls back to HTTP GET /submissions.
 * Will try X-Auth-Token header first and AuthToken header second.
 *
 * Returns an array of submission records (or empty array on failure).
 *
 * @param int $limit
 * @param array $filters Optional filters like ['status' => 'completed']
 * @return array
 */
function local_digisign_fetch_submissions($limit = 100, $filters = [], $useremail = '') {
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
            
            $params = ['limit' => (int)$limit];
            if (!empty($filters)) {
                $params = array_merge($params, $filters);
            }
            
            $resp = $client->listSubmissions($params);
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
            debugging('local_digisign: Docuseal SDK error while listing submissions: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // fall through to HTTP fallback
        }
    } else {
        $debug['attempts'][] = ['method' => 'sdk', 'note' => 'SDK class not present'];
    }

    // HTTP fallback
    $base = rtrim($cfg->api_url, '/');
    $url = $base . '/submissions';
    
    // Build query parameters
    $params = [];
    if (!empty($limit)) {
        $params['limit'] = (int)$limit;
    }
    if (!empty($filters)) {
        $params = array_merge($params, $filters);
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
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
        //  - [ { submission1 }, { submission2 } ]
        //  - { "submissions": [ ... ] }
        if (is_array($decoded) && array_key_exists('data', $decoded) && is_array($decoded['data'])) {
            local_digisign_set_last_fetch_debug($debug);
            return $decoded['data'];
        }
        if (is_array($decoded)) {
            $first = reset($decoded);
            if (is_array($first) && (isset($first['id']) || isset($first['slug']) || isset($first['status']))) {
                local_digisign_set_last_fetch_debug($debug);
                return $decoded;
            }
            if (array_key_exists('submissions', $decoded) && is_array($decoded['submissions'])) {
                local_digisign_set_last_fetch_debug($debug);
                return $decoded['submissions'];
            }
        }
    }

    // Nothing returned
    local_digisign_set_last_fetch_debug($debug);
    debugging('local_digisign: failed to fetch submissions. Attempts: ' . substr(print_r($debug, true), 0, 4000), DEBUG_DEVELOPER);
    return [];
}

/**
 * Get a specific submission by ID.
 *
 * Tries SDK first (if available), then falls back to HTTP GET /submissions/{id}.
 *
 * @param string|int $submissionid
 * @return array|null
 */
function local_digisign_get_submission($submissionid) {
    $cfg = local_digisign_get_config();

    // Try SDK first
    if (local_digisign_ensure_autoload() && class_exists('\\Docuseal\\Api')) {
        try {
            $client = new \Docuseal\Api($cfg->api_key, rtrim($cfg->api_url, '/'));
            $resp = $client->getSubmission((int)$submissionid);
            if (is_array($resp)) {
                return $resp;
            }
        } catch (Exception $e) {
            debugging('local_digisign: Docuseal SDK get submission error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // HTTP fallback: GET /submissions/{id} with X-Auth-Token header
    $url = rtrim($cfg->api_url, '/') . '/submissions/' . urlencode($submissionid);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Auth-Token: ' . $cfg->api_key,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $resp = curl_exec($ch);
    $curlerr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        debugging('local_digisign: curl error get submission: ' . $curlerr, DEBUG_DEVELOPER);
        return null;
    }

    if ($code >= 200 && $code < 300 && $resp) {
        $decoded = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }

    debugging('local_digisign: get_submission failed code=' . intval($code) . ' resp=' . substr($resp ?: '', 0, 1000), DEBUG_DEVELOPER);
    return null;
}

/**
 * Get submission status for a specific template and user.
 *
 * This function checks for submissions of a specific template by the current user
 * and determines the overall status considering multiple submitters.
 *
 * @param int $templateid
 * @param int $userid
 * @param string $useremail User email to filter submissions by (for operator role)
 * @return array Status information with keys: status, submission_id, submitters, etc.
 */
function local_digisign_get_template_submission_status($templateid, $userid, $useremail = '') {
    global $DB, $USER;
    
    // First check local database for any recorded submissions
    $local_records = $DB->get_records('local_digisign_sub', [
        'userid' => $userid,
        'templateid' => $templateid
    ], 'timecreated DESC');
    
    if (empty($local_records)) {
        return ['status' => 'none', 'submission_id' => null, 'submitters' => []];
    }
    
    // Get the most recent submission
    $latest_record = reset($local_records);
    $submission_id = $latest_record->submissionid;
    
    if (empty($submission_id)) {
        return ['status' => 'none', 'submission_id' => null, 'submitters' => []];
    }
    
    // Fetch detailed submission info from DocuSeal API
    $submission = local_digisign_get_submission($submission_id);
    
    if (!$submission) {
        // Fall back to local record status
        return [
            'status' => $latest_record->status,
            'submission_id' => $submission_id,
            'submitters' => [],
            'local_status' => $latest_record->status
        ];
    }
    
    // Extract submitters information and filter by user email for operator role
    $submitters = [];
    $all_completed = true;
    $any_created = false;
    $any_in_progress = false;
    $user_found = false;
    
    if (!empty($submission['submitters']) && is_array($submission['submitters'])) {
        foreach ($submission['submitters'] as $submitter) {
            $submitter_status = isset($submitter['status']) ? $submitter['status'] : 'unknown';
            $submitter_email = isset($submitter['email']) ? $submitter['email'] : '';
            $submitter_name = isset($submitter['name']) ? $submitter['name'] : $submitter_email;
            $submitter_role = isset($submitter['role']) ? strtolower($submitter['role']) : '';
            
            // Check if this is the current user with operator role
            if (!empty($useremail) && $submitter_email === $useremail && $submitter_role === 'operator') {
                $user_found = true;
            }
            
            $submitters[] = [
                'email' => $submitter_email,
                'name' => $submitter_name,
                'status' => $submitter_status,
                'role' => $submitter_role
            ];
            
            // Track overall status
            if ($submitter_status === 'completed') {
                // This submitter is done
            } elseif ($submitter_status === 'created') {
                $any_created = true;
                $all_completed = false;
            } elseif (in_array($submitter_status, ['pending', 'in_progress', 'sent'])) {
                $any_in_progress = true;
                $all_completed = false;
            } else {
                $all_completed = false;
            }
        }
    }
    
    // If user email is provided and user is not found in this submission, return none
    if (!empty($useremail) && !$user_found) {
        return ['status' => 'none', 'submission_id' => null, 'submitters' => []];
    }
    
    // Determine overall status
    $overall_status = 'unknown';
    if ($all_completed && !empty($submitters)) {
        $overall_status = 'completed';
    } elseif ($any_in_progress) {
        $overall_status = 'in_progress';
    } elseif ($any_created) {
        $overall_status = 'created';
    } else {
        // Check submission-level status
        $submission_status = isset($submission['status']) ? $submission['status'] : 'unknown';
        if ($submission_status === 'completed') {
            $overall_status = 'completed';
        } elseif ($submission_status === 'created') {
            $overall_status = 'created';
        } else {
            $overall_status = $submission_status;
        }
    }
    
    return [
        'status' => $overall_status,
        'submission_id' => $submission_id,
        'submitters' => $submitters,
        'submission_data' => $submission,
        'local_record' => $latest_record
    ];
}

/**
 * Get all template submission statuses for the current user.
 *
 * @param array $templates Array of template data from API
 * @param int $userid User ID to check submissions for
 * @return array Associative array with template_id as key and status info as value
 */
function local_digisign_get_all_template_statuses($templates, $userid, $useremail = '') {
    $statuses = [];
    
    foreach ($templates as $template) {
        $template_id = isset($template['id']) ? $template['id'] : 0;
        if ($template_id > 0) {
            $statuses[$template_id] = local_digisign_get_template_submission_status($template_id, $userid, $useremail);
        }
    }
    
    return $statuses;
}

/**
 * Set up secondary navigation for digisign pages.
 */
function local_digisign_set_secondarynav() {
    global $PAGE;
    
    $PAGE->set_secondary_navigation(true);
    $PAGE->set_secondary_active_tab("digisign");
}

/**
 * Extends the global navigation with OTA Digisign menu item.
 *
 * @param global_navigation $nav
 */
function local_digisign_extends_navigation(global_navigation $nav) {
    global $CFG, $PAGE;

    // Remove any existing digisign menu items to prevent duplicates
    $CFG->custommenuitems = preg_replace('/.*\/local\/digisign.*/', '', $CFG->custommenuitems);

    // Check if user is logged in and has access
    if (!isloggedin()) {
        return;
    }

    // Check if user has capability to view digisign
    $context = context_system::instance();
    if (!has_capability("local/digisign:view", $context)) {
        return;
    }

    try {
        // Add the Forms menu item to the navigation
        $name = "Forms";
        $url = "{$CFG->wwwroot}/local/digisign/";
        
        // Add to custommenuitems for main navigation
        $CFG->custommenuitems .= "\n{$name}|{$url}|Sign forms here";
        
        // Also add to the navigation tree
        $nav->add($name, new moodle_url($url), navigation_node::TYPE_CUSTOM, null, 'digisign_menu');
        
        // Clear navigation cache to ensure menu appears immediately
        try {
            navigation_cache::destroy_volatile_caches();
        } catch (Exception $e) {
            // Silently fail if cache clearing fails
        }
        
    } catch (Exception $e) {
        // Silently fail if navigation can't be extended
        debugging('local_digisign: Failed to extend navigation: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}