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
 * Event observer for plagiarism_uniwise.
 *
 * Listens for assessable_uploaded, assessable_submitted and quiz attempt_submitted
 * events to automatically send files to the external plagiarism service, and for
 * deletion events to clean up.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

/**
 * Observer class for plagiarism_uniwise events.
 */
class observer {
    /**
     * Handle the assessable_uploaded event (file submissions).
     *
     * @param \core\event\assessable_uploaded $event
     */
    public static function assessable_uploaded(\core\event\assessable_uploaded $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $config = get_config('plagiarism_uniwise');
        if (empty($config->originality_use)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        // Derive module type from context (handles submission plugin events like
        // assignsubmission_onlinetext whose component is not 'mod_assign').
        $cm = get_coursemodule_from_id('', $cmid);
        if (!$cm || !\plagiarism_plugin_uniwise::is_module_supported($cm->modname)) {
            return;
        }

        // Check if originality is enabled for this activity.
        $modsettings = $DB->get_record('plagiarism_uniwise_settings', ['cm' => $cmid]);
        if (empty($modsettings) || empty($modsettings->enabled)) {
            return;
        }

        // Skip if configured to submit on marking instead of upload (assignments only).
        if ($cm->modname === 'assign' && !empty($modsettings->submit_on)) {
            return;
        }

        $eventdata = $event->get_data();
        $userid = self::get_author_id($cm->modname, (int) $event->objectid, (int) $eventdata['userid']);

        // Get the files and content from the event.
        $other = $eventdata['other'] ?? [];

        // Handle online text content if present (e.g. from assignsubmission_onlinetext).
        $content = $other['content'] ?? '';
        if (!empty($content)) {
            plagiarism_uniwise_submit_text($content, $cmid, $userid);
        }

        // Handle file uploads.
        $pathnamehashes = $other['pathnamehashes'] ?? [];

        if (empty($pathnamehashes)) {
            return;
        }

        $fs = get_file_storage();
        foreach ($pathnamehashes as $hash) {
            $file = $fs->get_file_by_hash($hash);
            if ($file && !$file->is_directory()) {
                plagiarism_uniwise_submit_file($file, $cmid, $userid);
            }
        }
    }

    /**
     * Handle the assessable_submitted event (online text / final submission).
     *
     * @param \core\event\assessable_submitted $event
     */
    public static function assessable_submitted(\core\event\assessable_submitted $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $config = get_config('plagiarism_uniwise');
        if (empty($config->originality_use)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        // Derive module type from context for consistency with assessable_uploaded.
        $cm = get_coursemodule_from_id('', $cmid);
        if (!$cm || !\plagiarism_plugin_uniwise::is_module_supported($cm->modname)) {
            return;
        }

        $modsettings = $DB->get_record('plagiarism_uniwise_settings', ['cm' => $cmid]);
        if (empty($modsettings) || empty($modsettings->enabled)) {
            return;
        }

        // Skip if configured to submit on marking instead of upload (assignments only).
        if ($cm->modname === 'assign' && !empty($modsettings->submit_on)) {
            return;
        }

        $eventdata = $event->get_data();
        $userid = $eventdata['userid'];
        $other = $eventdata['other'] ?? [];

        // Handle online text content if present.
        $content = $other['content'] ?? '';
        if (!empty($content)) {
            plagiarism_uniwise_submit_text($content, $cmid, $userid);
        }

        // Handle any associated files.
        $pathnamehashes = $other['pathnamehashes'] ?? [];
        if (!empty($pathnamehashes)) {
            $fs = get_file_storage();
            foreach ($pathnamehashes as $hash) {
                $file = $fs->get_file_by_hash($hash);
                if ($file && !$file->is_directory()) {
                    plagiarism_uniwise_submit_file($file, $cmid, $userid);
                }
            }
        }
    }

    /**
     * Handle the submission_removed event.
     *
     * Deletes the corresponding documents from the external plagiarism service.
     *
     * @param \mod_assign\event\submission_removed $event
     */
    public static function submission_removed(\mod_assign\event\submission_removed $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $config = get_config('plagiarism_uniwise');
        if (empty($config->originality_use)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        // Check if this activity type is enabled in global settings.
        if (!\plagiarism_plugin_uniwise::is_module_supported('assign')) {
            return;
        }
        $userid = $event->relateduserid ?? $event->userid;

        // Find all tracked files for this user on this course module.
        $records = $DB->get_records('plagiarism_uniwise_files', [
            'cm'     => $cmid,
            'userid' => $userid,
        ]);

        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {
            plagiarism_uniwise_queue_delete($record);
        }
    }

    /**
     * Handle the quiz attempt_submitted event.
     *
     * Quizzes do not trigger assessable events, so essay answers and attachments
     * are collected from the submitted attempt.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $config = get_config('plagiarism_uniwise');
        if (empty($config->originality_use)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        if (!\plagiarism_plugin_uniwise::is_module_supported('quiz')) {
            return;
        }

        $modsettings = $DB->get_record('plagiarism_uniwise_settings', ['cm' => $cmid]);
        if (empty($modsettings) || empty($modsettings->enabled)) {
            return;
        }

        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid], 'id, uniqueid, userid, preview');
        if (!$attempt || !empty($attempt->preview)) {
            return;
        }
        $userid = (int) $attempt->userid;
        $context = \context_module::instance($cmid);

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            if ($qa->get_question()->get_type_name() !== 'essay') {
                continue;
            }

            // The essay renderer links reports by the response summary, so that identifies the text.
            if (onlinetext_finder::essay_answer_text($qa) !== '') {
                plagiarism_uniwise_submit_text((string) onlinetext_finder::essay_response_summary($qa), $cmid, $userid);
            }

            foreach ($qa->get_last_qt_files('attachments', $context->id) as $file) {
                if (!$file->is_directory()) {
                    plagiarism_uniwise_submit_file($file, $cmid, $userid);
                }
            }
        }
    }

    /**
     * Handle the course_module_deleted event.
     *
     * Removes the activity settings and deletes the activity's documents from the external service.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $cmid = (int) $event->objectid;
        $DB->delete_records('plagiarism_uniwise_settings', ['cm' => $cmid]);

        $records = $DB->get_records('plagiarism_uniwise_files', ['cm' => $cmid], '', 'id, externalid');
        foreach ($records as $record) {
            plagiarism_uniwise_queue_delete($record);
        }
    }

    /**
     * Handle the course_content_deleted event.
     *
     * Deleting a course removes its modules without triggering course_module_deleted,
     * so clean up any settings and documents whose course module no longer exists.
     *
     * @param \core\event\course_content_deleted $event
     */
    public static function course_content_deleted(\core\event\course_content_deleted $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $DB->delete_records_select('plagiarism_uniwise_settings', 'cm NOT IN (SELECT id FROM {course_modules})');

        // Failed deletions are left for the admin to retry from the failed tasks page.
        $records = $DB->get_records_sql(
            "SELECT f.id, f.externalid
               FROM {plagiarism_uniwise_files} f
          LEFT JOIN {course_modules} cm ON cm.id = f.cm
              WHERE cm.id IS NULL AND f.status <> :deletefailed",
            ['deletefailed' => 4]
        );
        foreach ($records as $record) {
            plagiarism_uniwise_queue_delete($record);
        }
    }

    /**
     * Get the author of the content behind an assessable event.
     *
     * Forum posts and workshop submissions can be edited by other users (e.g. teachers),
     * in which case the event user is the editor rather than the author.
     *
     * @param string $modname Module name.
     * @param int $objectid The event object ID (forum post or workshop submission ID).
     * @param int $fallback The user ID to use when no author is found.
     * @return int
     */
    private static function get_author_id(string $modname, int $objectid, int $fallback): int {
        global $DB;

        $authorid = match ($modname) {
            'forum' => $DB->get_field('forum_posts', 'userid', ['id' => $objectid]),
            'workshop' => $DB->get_field('workshop_submissions', 'authorid', ['id' => $objectid]),
            default => false,
        };
        return $authorid ? (int) $authorid : $fallback;
    }

    /**
     * Handle the submission_graded event.
     *
     * When submit_on is set to "on marking", this triggers plagiarism submission
     * for all files in the student's assignment submission.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

        $config = get_config('plagiarism_uniwise');
        if (empty($config->originality_use)) {
            return;
        }

        $cmid = $event->contextinstanceid;

        if (!\plagiarism_plugin_uniwise::is_module_supported('assign')) {
            return;
        }

        $modsettings = $DB->get_record('plagiarism_uniwise_settings', ['cm' => $cmid]);
        if (empty($modsettings) || empty($modsettings->enabled)) {
            return;
        }

        // Only process if submit_on is set to "on marking".
        if (empty($modsettings->submit_on)) {
            return;
        }

        $userid = $event->relateduserid;
        if (empty($userid)) {
            return;
        }

        // Get the assignment and the user's submission.
        $cm = get_coursemodule_from_id('assign', $cmid);
        if (!$cm) {
            return;
        }

        // Find the latest submitted submission for this user.
        $submissions = $DB->get_records_sql(
            "SELECT s.* FROM {assign_submission} s
              WHERE s.assignment = :assignid AND s.userid = :userid AND s.status = :status
              ORDER BY s.timemodified DESC",
            ['assignid' => $cm->instance, 'userid' => $userid, 'status' => 'submitted'],
            0,
            1
        );
        $submission = reset($submissions);

        if (!$submission) {
            return;
        }

        // Submit any file attachments.
        $fs = get_file_storage();
        $context = \context_module::instance($cmid);
        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'id',
            false
        );

        foreach ($files as $file) {
            if (!$file->is_directory()) {
                plagiarism_uniwise_submit_file($file, $cmid, $userid);
            }
        }

        // Submit online text if present.
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'assignment' => $cm->instance,
            'submission' => $submission->id,
        ]);
        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            plagiarism_uniwise_submit_text($onlinetext->onlinetext, $cmid, $userid);
        }
    }
}
