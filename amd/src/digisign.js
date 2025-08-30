define(['jquery'], function($) {
    return {
        init: function() {
            $(document).ready(function() {
                var $root = $('#local-digisign-root');
                if (!$root.length) {
                    return;
                }
                var useremail = $root.data('useremail') || '';

                $(document).on('click', '.digisign-tile .start-btn', function() {
                    var tile = $(this).closest('.digisign-tile');
                    var templateid = tile.data('templateid');
                    var templateslug = tile.data('templateslug');

                    $.ajax({
                        url: M.cfg.wwwroot + '/local/digisign/ajax.php',
                        method: 'POST',
                        data: {
                            action: 'create_submission',
                            sesskey: M.cfg.sesskey,
                            templateid: templateid
                        },
                        dataType: 'json'
                    }).done(function(resp) {
                        if (!resp || !resp.submitter_slug && !resp.submission_id) {
                            alert('Failed to create submission. See admin logs.');
                            return;
                        }
                        var submitter = resp.submitter_slug || resp.submission_id;
                        // embed URL pattern: adjust if you use different docuseal embedding path
                        var embedUrl = 'https://docuseal.com/s/' + submitter;

                        var $modal = $('#local-digisign-modal');
                        var $container = $('#local-digisign-widget');
                        $container.html('');

                        // ensure docuseal script exists
                        if (!document.querySelector('script[src="https://cdn.docuseal.com/js/form.js"]')) {
                            var s = document.createElement('script');
                            s.src = 'https://cdn.docuseal.com/js/form.js';
                            document.head.appendChild(s);
                        }

                        var el = document.createElement('docuseal-form');
                        el.setAttribute('data-src', embedUrl);
                        el.setAttribute('data-email', useremail);
                        // other attributes can be set: data-go-to-last, data-send-copy-email, etc.

                        $container.get(0).appendChild(el);

                        // Listen for completed event
                        el.addEventListener('completed', function(e) {
                            // call server to download and store
                            $.post(M.cfg.wwwroot + '/local/digisign/ajax.php', {
                                action: 'complete_submission',
                                submission_id: resp.submission_id || submitter,
                                sesskey: M.cfg.sesskey
                            }, function(r) {
                                if (r && r.success) {
                                    alert('Form completed and saved in your private files.');
                                    location.reload();
                                } else {
                                    alert('Completed but failed to store file. Contact admin.');
                                }
                            }, 'json');
                        });

                        $modal.show();
                    }).fail(function() {
                        alert('Request failed - check network or server logs.');
                    });
                });

                $(document).on('click', '#local-digisign-close', function() {
                    $('#local-digisign-modal').hide();
                    $('#local-digisign-widget').html('');
                });
            });
        }
    };
});