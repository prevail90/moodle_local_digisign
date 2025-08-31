<?php
// Language strings for local_digisign (Docuseal integration).

$string['pluginname'] = 'Digisign (Docuseal) integration';
$string['api_configuration'] = 'API Configuration';
$string['api_configuration_desc'] = 'Configure your DocuSeal API settings for integration with Moodle.';
$string['api_key'] = 'Docuseal API key';
$string['api_key_desc'] = 'Enter your Docuseal API key used for server-side API requests.';
$string['api_url'] = 'Docuseal API URL';
$string['api_url_desc'] = 'Docuseal API base URL (default https://sign.operatortraining.academy/api).';
$string['timeout'] = 'API Timeout';
$string['timeout_desc'] = 'Timeout in seconds for API requests (default 30). Increase if experiencing timeout issues.';
$string['choose_template'] = 'Choose a form/template to sign';
$string['start'] = 'Start';
$string['continue'] = 'Continue Submission';
$string['completed'] = 'Completed';
$string['close'] = 'Close';
$string['privacy:metadata'] = 'The Digisign plugin stores references to Docuseal submissions and which user completed them.';
$string['privacy:metadata:local_digisign_sub'] = 'Submission records for Docuseal integration.';

$string['notemplatesfound'] = 'No templates were found.';
$string['nopreview'] = 'No preview';
$string['untitled'] = 'Untitled';

$string['failed_create_submission'] = 'Failed to create a Docuseal submission. Please contact the administrator.';
$string['request_failed'] = 'Network or server error. Please try again later.';

// New strings for submissions functionality
$string['submissions'] = 'Submissions';
$string['nosubmissionsfound'] = 'No submissions were found.';
$string['view_submission'] = 'View submission';
$string['download_submission'] = 'Download submission';
$string['submission_status'] = 'Submission status';
$string['submission_created'] = 'Created';
$string['submission_updated'] = 'Updated';
$string['submission_template'] = 'Template';
$string['submission_actions'] = 'Actions';

// Status indicator strings
$string['status_not_started'] = 'Not Started';
$string['status_created'] = 'Created';
$string['status_in_progress'] = 'In Progress';
$string['status_completed'] = 'Completed';
$string['status_unknown'] = 'Unknown';

// Submitter information
$string['submitters_info'] = '{$a->completed} of {$a->total} completed';

// Submission management
$string['submission_not_found'] = 'Submission not found';
$string['submission_access_denied'] = 'Access denied. This submission does not belong to you.';
$string['submission_not_completed'] = 'This submission is not yet completed and cannot be downloaded.';
$string['submission_download_failed'] = 'Failed to download submission. Please try again later.';
$string['submission_details'] = 'Submission Details';
$string['submission_expired'] = 'This submission has expired or the signing link is no longer valid. Please create a new submission.';

// Sign page
$string['sign_new_submission'] = 'Sign New Submission';
$string['edit_submission'] = 'Edit Submission';
$string['sign_instructions'] = 'Please fill out and sign the form below. All required fields must be completed before you can submit.';
$string['loading_form'] = 'Loading form...';
$string['submission_completed'] = 'Submission completed successfully!';
$string['submission_completion_failed'] = 'Failed to complete submission. Please try again.';
$string['form_error'] = 'An error occurred while loading the form. Please try again.';
$string['invalid_template'] = 'Invalid template selected.';
$string['invalid_submission'] = 'Invalid submission selected.';