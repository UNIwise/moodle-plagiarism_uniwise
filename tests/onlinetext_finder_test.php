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
 * Unit tests for the plagiarism_uniwise online text finder.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

/**
 * Tests for the onlinetext_finder class.
 *
 * @covers \plagiarism_uniwise\onlinetext_finder
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\plagiarism_uniwise\onlinetext_finder::class)]
final class onlinetext_finder_test extends \advanced_testcase {
    /**
     * Test finding the text of a forum post.
     *
     * @covers \plagiarism_uniwise\onlinetext_finder::find
     */
    public function test_find_forum_post(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $student->id,
            'message' => 'My forum post',
        ]);

        $cm = get_coursemodule_from_id('', $forum->cmid);
        $this->assertSame('My forum post', onlinetext_finder::find($cm, $student->id, sha1('My forum post')));
        $this->assertNull(onlinetext_finder::find($cm, $student->id, sha1('Something else')));
    }

    /**
     * Test finding the text of a workshop submission.
     *
     * @covers \plagiarism_uniwise\onlinetext_finder::find
     */
    public function test_find_workshop_submission(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setAdminUser();
        $workshop = $this->getDataGenerator()->create_module('workshop', ['course' => $course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_workshop')->create_submission($workshop->id, $student->id, [
            'content' => 'My workshop submission',
        ]);

        $cm = get_coursemodule_from_id('', $workshop->cmid);
        $this->assertSame(
            'My workshop submission',
            onlinetext_finder::find($cm, $student->id, sha1('My workshop submission'))
        );
    }

    /**
     * Test finding the answer of a quiz essay question from its response summary.
     *
     * @covers \plagiarism_uniwise\onlinetext_finder::find
     * @covers \plagiarism_uniwise\onlinetext_finder::essay_answer_text
     */
    public function test_find_quiz_essay(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0, 'sumgrades' => 1]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $essay = $questiongenerator->create_question('essay', 'plain', ['category' => $category->id]);
        quiz_add_quiz_question($essay->id, $quiz);

        $this->setUser($student);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $quizgenerator->submit_responses($attempt->id, [1 => 'My essay answer'], false, true);

        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $summary = $attemptobj->get_question_attempt(1)->get_response_summary();

        $cm = get_coursemodule_from_id('', $quiz->cmid);
        $this->assertSame('My essay answer', onlinetext_finder::find($cm, $student->id, sha1($summary)));
        $this->assertNull(onlinetext_finder::find($cm, $student->id, sha1('Something else')));
    }

    /**
     * Test that unsupported modules return null.
     *
     * @covers \plagiarism_uniwise\onlinetext_finder::find
     */
    public function test_find_returns_null_for_unsupported_module(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $cm = get_coursemodule_from_id('', $page->cmid);
        $this->assertNull(onlinetext_finder::find($cm, 2, sha1('anything')));
    }
}
