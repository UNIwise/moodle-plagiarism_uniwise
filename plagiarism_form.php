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
 * Admin settings form for the UNIwise Originality plagiarism plugin.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Settings form for plagiarism_uniwise.
 */
class plagiarism_setup_form extends moodleform {
    /**
     * Define the form elements.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $mform->addElement('checkbox', 'originality_use', get_string('useoriginality', 'plagiarism_uniwise'));

        $mform->addElement(
            'textarea',
            'originality_student_disclosure',
            get_string('studentdisclosure', 'plagiarism_uniwise'),
            'wrap="virtual" rows="6" cols="50"'
        );
        $mform->addHelpButton('originality_student_disclosure', 'studentdisclosure', 'plagiarism_uniwise');
        $mform->setDefault('originality_student_disclosure', get_string('studentdisclosuredefault', 'plagiarism_uniwise'));

        // API connection settings.
        $mform->addElement('header', 'originality_api_header', get_string('apisettings', 'plagiarism_uniwise'));

        $mform->addElement('text', 'originality_api_url', get_string('apiurl', 'plagiarism_uniwise'), ['size' => 60]);
        $mform->setType('originality_api_url', PARAM_URL);
        $mform->addHelpButton('originality_api_url', 'apiurl', 'plagiarism_uniwise');
        $mform->addRule('originality_api_url', null, 'required', null, 'client');
        $mform->disabledIf('originality_api_url', 'originality_use');

        $mform->addElement('text', 'originality_client_id', get_string('clientid', 'plagiarism_uniwise'), ['size' => 60]);
        $mform->setType('originality_client_id', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('originality_client_id', 'clientid', 'plagiarism_uniwise');
        $mform->addRule('originality_client_id', null, 'required', null, 'client');
        $mform->disabledIf('originality_client_id', 'originality_use');

        $mform->addElement(
            'passwordunmask',
            'originality_client_secret',
            get_string('clientsecret', 'plagiarism_uniwise'),
            ['size' => 60]
        );
        $mform->setType('originality_client_secret', PARAM_RAW);
        $mform->addHelpButton('originality_client_secret', 'clientsecret', 'plagiarism_uniwise');
        $mform->addRule('originality_client_secret', null, 'required', null, 'client');
        $mform->disabledIf('originality_client_secret', 'originality_use');

        // Report visibility settings.
        $mform->addElement('header', 'originality_report_header', get_string('reportsettings', 'plagiarism_uniwise'));

        $mform->addElement('checkbox', 'originality_student_report', get_string('allow_student_report', 'plagiarism_uniwise'));
        $mform->addHelpButton('originality_student_report', 'allow_student_report', 'plagiarism_uniwise');
        $mform->setDefault('originality_student_report', 0);
        $mform->disabledIf('originality_student_report', 'originality_use');

        // Submission timing settings.
        $mform->addElement('header', 'originality_timing_header', get_string('timingsettings', 'plagiarism_uniwise'));

        $submitonoptions = [
            0 => get_string('submit_on_upload', 'plagiarism_uniwise'),
            1 => get_string('submit_on_marking', 'plagiarism_uniwise'),
        ];
        $mform->addElement('select', 'originality_submit_on', get_string('submit_on', 'plagiarism_uniwise'), $submitonoptions);
        $mform->addHelpButton('originality_submit_on', 'submit_on', 'plagiarism_uniwise');
        $mform->setDefault('originality_submit_on', 0);
        $mform->disabledIf('originality_submit_on', 'originality_use');

        // Indexing settings.
        $mform->addElement('header', 'originality_index_header', get_string('indexsettings', 'plagiarism_uniwise'));

        $mform->addElement('checkbox', 'originality_index_documents', get_string('index_documents', 'plagiarism_uniwise'));
        $mform->addHelpButton('originality_index_documents', 'index_documents', 'plagiarism_uniwise');
        $mform->setDefault('originality_index_documents', 0);
        $mform->disabledIf('originality_index_documents', 'originality_use');

        // Activity type settings.
        $mform->addElement('header', 'originality_mods_header', get_string('activitiessettings', 'plagiarism_uniwise'));

        $mform->addElement('checkbox', 'originality_mod_assign', get_string('enable_mod_assign', 'plagiarism_uniwise'));
        $mform->setDefault('originality_mod_assign', 1);
        $mform->disabledIf('originality_mod_assign', 'originality_use');

        $mform->addElement('checkbox', 'originality_mod_forum', get_string('enable_mod_forum', 'plagiarism_uniwise'));
        $mform->setDefault('originality_mod_forum', 0);
        $mform->disabledIf('originality_mod_forum', 'originality_use');

        $mform->addElement('checkbox', 'originality_mod_workshop', get_string('enable_mod_workshop', 'plagiarism_uniwise'));
        $mform->setDefault('originality_mod_workshop', 0);
        $mform->disabledIf('originality_mod_workshop', 'originality_use');

        $mform->addElement('checkbox', 'originality_mod_quiz', get_string('enable_mod_quiz', 'plagiarism_uniwise'));
        $mform->setDefault('originality_mod_quiz', 0);
        $mform->disabledIf('originality_mod_quiz', 'originality_use');

        $this->add_action_buttons(true);
    }
}
