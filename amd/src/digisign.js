define(['jquery', 'core/str', 'core/ajax'], function($, Str, Ajax) {
    /**
     * AMD module for local_digisign - handles button clicks and API submission creation.
     *
     * Responsibilities:
     * - Load language strings via core/str
     * - Handle button clicks for creating/editing submissions
     * - Create submissions via AJAX API calls
     * - Redirect to DocuSeal signing URLs
     */

    console.log('local_digisign: AMD module loaded successfully');

    var strings = {};

    function loadStrings() {
        return Str.get_strings([
            {key: 'failed_create_submission', component: 'local_digisign'},
            {key: 'request_failed', component: 'local_digisign'},
            {key: 'notemplatesfound', component: 'local_digisign'},
            {key: 'nopreview', component: 'local_digisign'},
            {key: 'untitled', component: 'local_digisign'},
            {key: 'completed', component: 'local_digisign'},
            {key: 'start', component: 'local_digisign'},
            {key: 'close', component: 'local_digisign'}
        ]).then(function(res) {
            strings.failed_create_submission = res[0];
            strings.request_failed = res[1];
            strings.notemplatesfound = res[2];
            strings.nopreview = res[3];
            strings.untitled = res[4];
            strings.completed = res[5];
            strings.start = res[6];
            strings.close = res[7];
        });
    }

    function createSubmission(templateId, templateSlug) {
        console.log('local_digisign: Creating submission for template:', templateId, templateSlug);
        
        // Show loading state
        var $btn = $('.digisign-tile[data-template-slug="' + templateSlug + '"] .create-submission');
        var originalText = $btn.text();
        $btn.text('Creating...').prop('disabled', true);
        
        // Make AJAX call to create submission
        $.ajax({
            url: M.cfg.wwwroot + '/local/digisign/ajax.php',
            method: 'POST',
            data: {
                action: 'create_submission_ajax',
                template_id: templateId,
                template_slug: templateSlug,
                sesskey: M.cfg.sesskey
            },
            dataType: 'json'
        }).then(function(response) {
            console.log('local_digisign: Submission created successfully:', response);
            
            if (response.success && response.template_slug) {
                // Redirect to DocuSeal signing URL using template slug for new submissions
                var signingUrl = response.docuseal_base + '/d/' + response.template_slug;
                console.log('local_digisign: Redirecting to DocuSeal:', signingUrl);
                window.location.href = signingUrl;
            } else {
                throw new Error(response.error || 'No template slug received');
            }
        }).catch(function(error) {
            console.error('local_digisign: Failed to create submission:', error);
            alert(strings.failed_create_submission || 'Failed to create submission');
            
            // Reset button state
            $btn.text(originalText).prop('disabled', false);
        });
    }

    function attachHandlers() {
        var $root = $('#local-digisign-root');
        if (!$root.length) {
            console.log('local_digisign: Root container not found');
            return;
        }
        
        console.log('local_digisign: Attaching handlers');

        // Create new submission button clicked
        $(document).on('click', '.digisign-tile .create-submission', function() {
            console.log('local_digisign: Create submission button clicked');
            var $btn = $(this);
            var tile = $btn.closest('.digisign-tile');
            var templateId = tile.data('templateid');
            var templateSlug = tile.data('template-slug');
            
            console.log('local_digisign: Template ID:', templateId, 'Slug:', templateSlug);

            if (templateId && templateSlug) {
                createSubmission(templateId, templateSlug);
            } else {
                console.error('local_digisign: Missing template ID or slug');
                alert('Template information not found');
            }
        });

        // Open existing submission button clicked
        $(document).on('click', '.digisign-tile .open-submission', function() {
            var $btn = $(this);
            var submissionid = $btn.data('submissionid');

            if (!submissionid) {
                alert('No submission ID found');
                return;
            }

            // For existing submissions, we'll need to get the submitter slug
            // This could be done via AJAX or stored in data attributes
            console.log('local_digisign: Opening existing submission:', submissionid);
            // TODO: Implement existing submission handling
        });

        // Legacy start button handler (for backward compatibility)
        $(document).on('click', '.digisign-tile .start-btn:not(.create-submission):not(.open-submission)', function() {
            var $btn = $(this);
            var tile = $btn.closest('.digisign-tile');
            var templateId = tile.data('templateid');
            var templateSlug = tile.data('template-slug');

            if (templateId && templateSlug) {
                createSubmission(templateId, templateSlug);
            } else {
                console.error('local_digisign: Missing template ID or slug for start button');
                alert('Template information not found');
            }
        });

        // Edit submission button (from view submission page)
        $(document).on('click', '.edit-submission', function() {
            var $btn = $(this);
            var submissionid = $btn.data('id');

            if (!submissionid) {
                alert('No submission ID found');
                return;
            }

            console.log('local_digisign: Editing submission:', submissionid);
            // TODO: Implement edit submission handling
        });
    }

    return {
        init: function() {
            console.log('local_digisign: Module initialized');
            loadStrings().then(function() {
                console.log('local_digisign: Strings loaded successfully');
                attachHandlers();
            }).catch(function() {
                console.log('local_digisign: Using fallback strings');
                // load fallback strings if get_strings fails
                strings.failed_create_submission = strings.failed_create_submission || 'Failed to create submission';
                strings.request_failed = strings.request_failed || 'Request failed';
                strings.notemplatesfound = strings.notemplatesfound || 'No templates found.';
                strings.nopreview = strings.nopreview || 'No preview';
                strings.untitled = strings.untitled || 'Untitled';
                strings.completed = strings.completed || 'Completed';
                strings.start = strings.start || 'Start';
                strings.close = strings.close || 'Close';
                attachHandlers();
            });
        }
    };
});