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
 * Backup and restore tests for plagiarism_uniwise.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/plagiarism/uniwise/lib.php');

/**
 * Tests that per-activity settings survive backup and restore.
 *
 * @covers \backup_plagiarism_uniwise_plugin
 * @covers \restore_plagiarism_uniwise_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Duplicating an activity keeps its Originality settings.
     */
    public function test_duplicate_module_retains_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('enableplagiarism', 1);
        set_config('originality_use', 1, 'plagiarism_uniwise');
        set_config('enabled', 1, 'plagiarism_uniwise');
        set_config('originality_mod_assign', 1, 'plagiarism_uniwise');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);

        $data = (object) [
            'coursemodule' => $cm->id,
            'originality_enabled' => 1,
            'originality_student_report' => 1,
            'originality_submit_on' => 1,
            'originality_index_documents' => 1,
        ];
        (new \plagiarism_plugin_uniwise())->save_activity_settings($data);

        $newcm = duplicate_module($course, $cm);

        $record = $DB->get_record('plagiarism_uniwise_settings', ['cm' => $newcm->id], '*', MUST_EXIST);
        $this->assertEquals(1, $record->enabled);
        $this->assertEquals(1, $record->student_report);
        $this->assertEquals(1, $record->submit_on);
        $this->assertEquals(1, $record->index_documents);
    }
}
