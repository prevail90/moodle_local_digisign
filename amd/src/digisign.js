define(['jquery', 'core/str'], function($, Str) {
    /**
     * AMD module for local_digisign - Docuseal web-component embed.
     *
     * Responsibilities:
     * - Load language strings via core/str
     * - Load Docuseal embed script (https://cdn.docuseal.com/js/form.js) on demand
     * - Create a <docuseal-form> element with data-src/data-email inside the modal
     * - Attach a 'completed' listener to that element instance and call server-side AJAX
     * - Clean up element/listeners on modal close
     *
     * Server endpoints expected:
     * - POST /local/digisign/ajax.php?action=create_submission (returns JSON with submission_id and submitter_slug)
     * - POST /local/digisign/ajax.php?action=complete_submission (expects submission_id)
     *
     * Note: Keep the server-side ajax.php and lib functions in sync with these expectations.
     */

    var strings = {};
    var currentEmbedEl = null;
    var currentCompletedHandler = null;

    function loadStrings() {
        return Str.get_strings([
            {key: 'failed_create_submission', component: 'local_digisign'},
            {key: 'failed_store_file', component: 'local_digisign'},
            {key: 'request_failed', component: 'local_digisign'},
            {key: 'notemplatesfound', component: 'local_digisign'},
            {key: 'nopreview', component: 'local_digisign'},
            {key: 'untitled', component: 'local_digisign'},
            {key: 'completed', component: 'local_digisign'},
            {key: 'start', component: 'local_digisign'},
            {key: 'close', component: 'local_digisign'}
        ]).then(function(res) {
            strings.failed_create_submission = res[0];
            strings.failed_store_file = res[1];
            strings.request_failed = res[2];
            strings.notemplatesfound = res[3];
            strings.nopreview = res[4];
            strings.untitled = res[5];
            strings.completed = res[6];
            strings.start = res[7];
            strings.close = res[8];
        });
    }

    function ensureDocusealScript() {
        return new Promise(function(resolve, reject) {
            var src = 'https://cdn.docuseal.com/js/form.js';
            if (document.querySelector('script[src="' + src + '"]')) {
                // script already present — but the custom element may not yet be defined; wait a tick
                return resolve();
            }
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = function() {
                // slight delay to allow custom element registration if needed
                setTimeout(resolve, 20);
            };
            s.onerror = function() {
                reject(new Error('docuseal script failed to load'));
            };
            document.head.appendChild(s);
        });
    }

    function createSubmission(templateid) {
        return $.ajax({
            url: M.cfg.wwwroot + '/local/digisign/ajax.php',
            method: 'POST',
            data: {
                action: 'create_submission',
                sesskey: M.cfg.sesskey,
                templateid: templateid
            },
            dataType: 'json'
        });
    }

    function completeSubmission(submissionid) {
        return $.ajax({
            url: M.cfg.wwwroot + '/local/digisign/ajax.php',
            method: 'POST',
            data: {
                action: 'complete_submission',
                sesskey: M.cfg.sesskey,
                submission_id: submissionid
            },
            dataType: 'json'
        });
    }

    function openModalWithWebComponent(embedUrl, submissionid, useremail) {
        var $modal = $('#local-digisign-modal');
        var $container = $('#local-digisign-widget');

        // Clean up any previous embed and handlers first
        if (currentEmbedEl) {
            try {
                if (currentCompletedHandler && currentEmbedEl.removeEventListener) {
                    currentEmbedEl.removeEventListener('completed', currentCompletedHandler);
                }
            } catch (e) {
                // ignore
            }
            $container.html('');
            currentEmbedEl = null;
            currentCompletedHandler = null;
        }

        // Ensure docuseal script is available then create the element
        ensureDocusealScript().then(function() {
            // Create the web-component element
            var el = document.createElement('docuseal-form');
            el.setAttribute('data-src', embedUrl);
            if (useremail) {
                el.setAttribute('data-email', useremail);
            }
            // optionally set other attributes (e.g. data-width/data-height) if supported

            // Completed event handler on this element instance
            var onCompleted = function(e) {
                // remove listener immediately to avoid duplicate handling
                try {
                    el.removeEventListener('completed', onCompleted);
                } catch (er) {}
                currentCompletedHandler = null;

                // Call server to fetch/store signed PDF
                completeSubmission(submissionid).done(function(r) {
                    if (r && r.success) {
                        // Inform user and reload to show completed state
                        alert(strings.completed + (r.message ? ': ' + r.message : ''));
                        // mark locally (server should also record) and reload
                        location.reload();
                    } else {
                        alert(strings.failed_store_file);
                    }
                }).fail(function() {
                    alert(strings.request_failed);
                });
            };

            el.addEventListener('completed', onCompleted);

            currentEmbedEl = el;
            currentCompletedHandler = onCompleted;

            $container.get(0).appendChild(el);
            $modal.show();
        }).catch(function() {
            alert(strings.request_failed);
        });
    }

    function attachHandlers() {
        var $root = $('#local-digisign-root');
        if (!$root.length) {
            return;
        }
        var useremail = $root.data('useremail') || '';

        // Start button clicked
        $(document).on('click', '.digisign-tile .start-btn', function() {
            var $btn = $(this);
            var tile = $btn.closest('.digisign-tile');
            var templateid = tile.data('templateid');
            var templateslug = tile.data('templateslug');

            // UI feedback
            $btn.prop('disabled', true);
            var originalText = $btn.text();
            $btn.text('...');

            createSubmission(templateid).done(function(resp) {
                if (!resp || (!resp.submitter_slug && !resp.submission_id)) {
                    alert(strings.failed_create_submission);
                    $btn.prop('disabled', false).text(originalText);
                    return;
                }

                // resp should contain submitter_slug or submission_id
                var submitter = resp.submitter_slug || resp.submission_id;
                // Build embed URL. Adjust host/path if you host docuseal elsewhere.
                var embedUrl = 'https://docuseal.com/s/' + submitter;
                var submissionid = resp.submission_id || submitter;

                // Open modal with the web-component embed
                openModalWithWebComponent(embedUrl, submissionid, useremail);

                $btn.prop('disabled', false).text(originalText);
            }).fail(function() {
                alert(strings.request_failed);
                $btn.prop('disabled', false).text(originalText);
            });
        });

        // Close modal handler
        $(document).on('click', '#local-digisign-close', function() {
            var $modal = $('#local-digisign-modal');
            var $container = $('#local-digisign-widget');

            // Remove element and detach event listener
            if (currentEmbedEl && currentCompletedHandler) {
                try {
                    currentEmbedEl.removeEventListener('completed', currentCompletedHandler);
                } catch (e) {}
            }
            $container.html('');
            currentEmbedEl = null;
            currentCompletedHandler = null;
            $modal.hide();
        });
    }

    return {
        init: function() {
            loadStrings().then(function() {
                attachHandlers();
            }).catch(function() {
                // load fallback strings if get_strings fails
                strings.failed_create_submission = strings.failed_create_submission || 'Failed to create submission';
                strings.failed_store_file = strings.failed_store_file || 'Failed to store signed file';
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