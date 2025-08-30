<?php
namespace local_digisign\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for local_digisign (GDPR).
 */
class provider implements \core_privacy\local\metadata\null_provider, \core_privacy\local\metadata\provider {
    /**
     * Return meta-data about the plugin's data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function _get_metadata(collection $collection) : collection {
        $collection->add_database_table('local_digisign_sub', [
            'userid' => 'User id',
            'templateid' => 'Template id used in Docuseal',
            'submissionid' => 'Docuseal submission id',
            'submitterslug' => 'Submitter identifier/slug',
            'status' => 'Submission status (created/completed)'
        ], 'privacy:metadata:local_digisign_sub');
        return $collection;
    }

    // For minimal prototype implement null_provider and metadata provider only.
    public static function get_reason() : string {
        return 'Plugin stores only submission references and no private profile data.';
    }
}