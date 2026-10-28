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
 * Locates the online text behind a plagiarism_uniwise_files record.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

/**
 * Finds the text a user wrote in an activity by matching the sha1 identifier stored for the record.
 *
 * The identifier is the sha1 of the content that the activity passes to plagiarism_get_links(),
 * so each supported activity is searched for the user's text that hashes to it.
 */
class onlinetext_finder {
    /**
     * Find the online text matching an identifier.
     *
     * @param \stdClass $cm Course module record (with modname).
     * @param int $userid The user who wrote the text.
     * @param string $identifier The sha1 identifier stored for the record.
     * @return string|null The text to submit, or null if it no longer exists.
     */
    public static function find(\stdClass $cm, int $userid, string $identifier): ?string {
        global $DB;

        if ($cm->modname === 'quiz') {
            return self::find_quiz_essay($cm, $userid, $identifier);
        }

        $candidates = match ($cm->modname) {
            'assign' => $DB->get_fieldset_sql(
                "SELECT ot.onlinetext
                   FROM {assignsubmission_onlinetext} ot
                   JOIN {assign_submission} s ON s.id = ot.submission
                  WHERE ot.assignment = :assignment AND s.userid = :userid",
                ['assignment' => $cm->instance, 'userid' => $userid]
            ),
            'forum' => $DB->get_fieldset_sql(
                "SELECT p.message
                   FROM {forum_posts} p
                   JOIN {forum_discussions} d ON d.id = p.discussion
                  WHERE d.forum = :forum AND p.userid = :userid",
                ['forum' => $cm->instance, 'userid' => $userid]
            ),
            'workshop' => $DB->get_fieldset_select(
                'workshop_submissions',
                'content',
                'workshopid = :workshop AND authorid = :userid',
                ['workshop' => $cm->instance, 'userid' => $userid]
            ),
            default => [],
        };

        foreach ($candidates as $text) {
            if ($text !== null && sha1($text) === $identifier) {
                return $text;
            }
        }
        return null;
    }

    /**
     * Find the essay answer in the user's quiz attempts whose response summary matches the identifier.
     *
     * The essay renderer passes the response summary to plagiarism_get_links(), so that is what the
     * identifier is computed from, but only the answer text itself is submitted.
     *
     * @param \stdClass $cm Course module record.
     * @param int $userid The user who made the attempts.
     * @param string $identifier The sha1 of the response summary.
     * @return string|null The plain text answer, or null if not found.
     */
    private static function find_quiz_essay(\stdClass $cm, int $userid, string $identifier): ?string {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $attempts = $DB->get_records(
            'quiz_attempts',
            ['quiz' => $cm->instance, 'userid' => $userid, 'preview' => 0],
            'attempt DESC',
            'id, uniqueid'
        );
        foreach ($attempts as $attempt) {
            $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            foreach ($quba->get_slots() as $slot) {
                $qa = $quba->get_question_attempt($slot);
                if ($qa->get_question()->get_type_name() !== 'essay') {
                    continue;
                }
                $summary = self::essay_response_summary($qa);
                if ($summary !== null && sha1($summary) === $identifier) {
                    return self::essay_answer_text($qa);
                }
            }
        }
        return null;
    }

    /**
     * Get the response summary of an essay answer, as the essay renderer passes it to plagiarism_get_links().
     *
     * Since Moodle 5.0 the attempt_submitted event fires before the attempt is graded, so the summary is
     * not stored yet; compute it from the last response the same way finishing the question does.
     *
     * @param \question_attempt $qa The essay question attempt.
     * @return string|null
     */
    public static function essay_response_summary(\question_attempt $qa): ?string {
        $summary = $qa->get_response_summary();
        if ($summary !== null) {
            return $summary;
        }
        return $qa->get_question()->summarise_response($qa->get_last_step()->get_qt_data());
    }

    /**
     * Get the plain text of an essay answer, without the attachment list included in the response summary.
     *
     * @param \question_attempt $qa The essay question attempt.
     * @return string
     */
    public static function essay_answer_text(\question_attempt $qa): string {
        return trim(\question_utils::to_plain_text(
            (string) $qa->get_last_qt_var('answer', ''),
            (int) $qa->get_last_qt_var('answerformat', FORMAT_HTML),
            ['para' => false]
        ));
    }
}
