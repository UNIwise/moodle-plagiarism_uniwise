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
 * Privacy provider tests for plagiarism_uniwise.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use plagiarism_uniwise\task\delete_from_originality;

/**
 * Tests for the privacy provider.
 *
 * @covers \plagiarism_uniwise\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Insert a tracked submission record.
     *
     * @param int $cmid Course module ID.
     * @param int $userid User ID.
     * @param string|null $externalid External document ID.
     * @return int The record ID.
     */
    private function create_record(int $cmid, int $userid, ?string $externalid = 'ext-1'): int {
        global $DB;
        return $DB->insert_record('plagiarism_uniwise_files', (object) [
            'cm' => $cmid,
            'userid' => $userid,
            'identifier' => 'hash',
            'filename' => 'essay.pdf',
            'submissiontype' => 'file',
            'externalid' => $externalid,
            'score' => 12,
            'status' => 2,
            'attempts' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Metadata declares the local table and the external location.
     *
     * @covers \plagiarism_uniwise\privacy\provider::get_metadata
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('plagiarism_uniwise'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);

        $this->assertContains('plagiarism_uniwise_files', $names);
        $this->assertContains('plagiarism_uniwise_client', $names);
    }

    /**
     * User data is exported for the module context.
     *
     * @covers \plagiarism_uniwise\privacy\provider::export_plagiarism_user_data
     */
    public function test_export_plagiarism_user_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->create_record($assign->cmid, $student->id);

        $context = \context_module::instance($assign->cmid);
        provider::export_plagiarism_user_data($student->id, $context, [], []);

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_related_data([], 'plagiarism_uniwise');
        $this->assertCount(1, $data->submissions);
        $this->assertEquals('essay.pdf', $data->submissions[0]->filename);
    }

    /**
     * Deleting for a context removes all records and queues external deletion.
     *
     * @covers \plagiarism_uniwise\privacy\provider::delete_plagiarism_for_context
     */
    public function test_delete_plagiarism_for_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->create_record($assign->cmid, $student1->id, 'ext-1');
        $this->create_record($assign->cmid, $student2->id, null);
        $this->create_record($other->cmid, $student1->id, 'ext-3');

        provider::delete_plagiarism_for_context(\context_module::instance($assign->cmid));

        $this->assertFalse($DB->record_exists('plagiarism_uniwise_files', ['cm' => $assign->cmid]));
        $this->assertTrue($DB->record_exists('plagiarism_uniwise_files', ['cm' => $other->cmid]));
        $tasks = \core\task\manager::get_adhoc_tasks(delete_from_originality::class);
        $this->assertCount(1, $tasks);
        $this->assertEquals('ext-1', reset($tasks)->get_custom_data()->external_id);
    }

    /**
     * Deleting for a user only removes that user's records.
     *
     * @covers \plagiarism_uniwise\privacy\provider::delete_plagiarism_for_user
     */
    public function test_delete_plagiarism_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->create_record($assign->cmid, $student1->id);
        $this->create_record($assign->cmid, $student2->id);

        provider::delete_plagiarism_for_user($student1->id, \context_module::instance($assign->cmid));

        $this->assertFalse($DB->record_exists('plagiarism_uniwise_files', ['userid' => $student1->id]));
        $this->assertTrue($DB->record_exists('plagiarism_uniwise_files', ['userid' => $student2->id]));
    }

    /**
     * Deleting for a list of users only removes those users' records.
     *
     * @covers \plagiarism_uniwise\privacy\provider::delete_plagiarism_for_users
     */
    public function test_delete_plagiarism_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->create_record($assign->cmid, $student1->id);
        $this->create_record($assign->cmid, $student2->id);
        $this->create_record($assign->cmid, $student3->id);

        provider::delete_plagiarism_for_users([$student1->id, $student2->id], \context_module::instance($assign->cmid));

        $this->assertEquals(1, $DB->count_records('plagiarism_uniwise_files', ['cm' => $assign->cmid]));
        $this->assertTrue($DB->record_exists('plagiarism_uniwise_files', ['userid' => $student3->id]));
    }
}
