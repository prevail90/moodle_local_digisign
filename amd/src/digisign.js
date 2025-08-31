define(['jquery', 'core/str'], function($, Str) {
    /**
     * AMD module for local_digisign - handles button clicks and redirects to sign.php.
     *
     * Responsibilities:
     * - Load language strings via core/str
     * - Handle button clicks for creating/editing submissions
     * - Redirect to sign.php page for form handling
     */

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

    function attachHandlers() {
        var $root = $('#local-digisign-root');
        if (!$root.length) {
            return;
        }

        // Create new submission button clicked
        $(document).on('click', '.digisign-tile .create-submission', function() {
            var $btn = $(this);
            var tile = $btn.closest('.digisign-tile');
            var templateid = tile.data('templateid');

            // Redirect to sign page for new submission
            var signUrl = M.cfg.wwwroot + '/local/digisign/sign.php?action=new&template=' + templateid;
            window.location.href = signUrl;
        });

        // Open existing submission button clicked
        $(document).on('click', '.digisign-tile .open-submission', function() {
            var $btn = $(this);
            var submissionid = $btn.data('submissionid');

            if (!submissionid) {
                alert('No submission ID found');
                return;
            }

            // Redirect to sign page for existing submission
            var signUrl = M.cfg.wwwroot + '/local/digisign/sign.php?action=edit&id=' + submissionid;
            window.location.href = signUrl;
        });

        // Legacy start button handler (for backward compatibility)
        $(document).on('click', '.digisign-tile .start-btn:not(.create-submission):not(.open-submission)', function() {
            var $btn = $(this);
            var tile = $btn.closest('.digisign-tile');
            var templateid = tile.data('templateid');

            // Redirect to sign page for new submission
            var signUrl = M.cfg.wwwroot + '/local/digisign/sign.php?action=new&template=' + templateid;
            window.location.href = signUrl;
        });

        // Edit submission button (from view submission page)
        $(document).on('click', '.edit-submission', function() {
            var $btn = $(this);
            var submissionid = $btn.data('id');

            if (!submissionid) {
                alert('No submission ID found');
                return;
            }

            // Redirect to sign page for editing submission
            var signUrl = M.cfg.wwwroot + '/local/digisign/sign.php?action=edit&id=' + submissionid;
            window.location.href = signUrl;
        });
    }

    return {
        init: function() {
            loadStrings().then(function() {
                attachHandlers();
            }).catch(function() {
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