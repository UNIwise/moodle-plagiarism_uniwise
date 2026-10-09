<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for plagiarism_uniwise.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for plagiarism_uniwise.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_plagiarism\privacy\plagiarism_provider,
    \core_plagiarism\privacy\plagiarism_user_provider {
    /**
     * Describe the personal data stored locally and sent to the external service.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('plagiarism_uniwise_files', [
            'cm' => 'privacy:metadata:plagiarism_uniwise_files:cm',
            'userid' => 'privacy:metadata:plagiarism_uniwise_files:userid',
            'identifier' => 'privacy:metadata:plagiarism_uniwise_files:identifier',
            'filename' => 'privacy:metadata:plagiarism_uniwise_files:filename',
            'externalid' => 'privacy:metadata:plagiarism_uniwise_files:externalid',
            'reporturl' => 'privacy:metadata:plagiarism_uniwise_files:reporturl',
            'score' => 'privacy:metadata:plagiarism_uniwise_files:score',
            'status' => 'privacy:metadata:plagiarism_uniwise_files:status',
            'errorresponse' => 'privacy:metadata:plagiarism_uniwise_files:errorresponse',
            'timecreated' => 'privacy:metadata:plagiarism_uniwise_files:timecreated',
            'timemodified' => 'privacy:metadata:plagiarism_uniwise_files:timemodified',
        ], 'privacy:metadata:plagiarism_uniwise_files');

        $collection->add_external_location_link('plagiarism_uniwise_client', [
            'userid' => 'privacy:metadata:plagiarism_uniwise_client:userid',
            'courseid' => 'privacy:metadata:plagiarism_uniwise_client:courseid',
            'coursemoduleid' => 'privacy:metadata:plagiarism_uniwise_client:coursemoduleid',
            'filename' => 'privacy:metadata:plagiarism_uniwise_client:filename',
            'submission_content' => 'privacy:metadata:plagiarism_uniwise_client:submission_content',
        ], 'privacy:metadata:plagiarism_uniwise_client');

        return $collection;
    }

    /**
     * Export all plagiarism data for the specified user and context.
     *
     * @param int $userid The user to export.
     * @param \context $context The context to export.
     * @param array $subcontext The subcontext within the context to export this information to.
     * @param array $linkarray The link array used to display information for a specific item.
     */
    public static function export_plagiarism_user_data(int $userid, \context $context, array $subcontext, array $linkarray) {
        global $DB;

        if (empty($userid) || $context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $records = $DB->get_records('plagiarism_uniwise_files', [
            'cm' => $context->instanceid,
            'userid' => $userid,
        ], 'id', 'id, filename, submissiontype, externalid, reporturl, score, status, timecreated, timemodified');

        if (empty($records)) {
            return;
        }

        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'filename' => $record->filename,
                'submissiontype' => $record->submissiontype,
                'externalid' => $record->externalid,
                'reporturl' => $record->reporturl,
                'score' => $record->score,
                'status' => $record->status,
                'timecreated' => transform::datetime($record->timecreated),
                'timemodified' => transform::datetime($record->timemodified),
            ];
        }

        writer::with_context($context)->export_related_data(
            $subcontext,
            'plagiarism_uniwise',
            (object) ['submissions' => $data]
        );
    }

    /**
     * Delete all user information for the provided context.
     *
     * @param \context $context The context to delete user data for.
     */
    public static function delete_plagiarism_for_context(\context $context) {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        self::delete_records(['cm' => $context->instanceid]);
    }

    /**
     * Delete all user information for the provided user and context.
     *
     * @param int $userid The user to delete.
     * @param \context $context The context to refine the deletion.
     */
    public static function delete_plagiarism_for_user(int $userid, \context $context) {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        self::delete_records(['cm' => $context->instanceid, 'userid' => $userid]);
    }

    /**
     * Delete all user information for the provided users and context.
     *
     * @param array $userids The users to delete.
     * @param \context $context The context to refine the deletion.
     */
    public static function delete_plagiarism_for_users(array $userids, \context $context) {
        if (empty($userids) || $context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        foreach ($userids as $userid) {
            self::delete_records(['cm' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Delete local records and queue deletion of the documents from the external service.
     *
     * @param array $conditions Conditions for plagiarism_uniwise_files.
     */
    protected static function delete_records(array $conditions): void {
        global $DB;

        $records = $DB->get_records('plagiarism_uniwise_files', $conditions, '', 'id, externalid');
        foreach ($records as $record) {
            if (!empty($record->externalid)) {
                // The local record is removed now, so the task gets record_id 0.
                $task = new \plagiarism_uniwise\task\delete_from_originality();
                $task->set_custom_data([
                    'external_id' => $record->externalid,
                    'record_id' => 0,
                    'attempt' => 1,
                ]);
                \core\task\manager::queue_adhoc_task($task);
            }
        }

        $DB->delete_records('plagiarism_uniwise_files', $conditions);
    }
}
