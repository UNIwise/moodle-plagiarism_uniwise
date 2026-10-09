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
 * Language strings for the UNIwise Originality plagiarism plugin.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activitiessettings'] = 'Supported activities';
$string['allow_student_report'] = 'Allow students to view reports';
$string['allow_student_report_activity'] = 'Allow students to view their reports';
$string['allow_student_report_activity_help'] = 'When enabled, students who submitted work in this activity can view their own plagiarism report.';
$string['allow_student_report_help'] = 'When enabled, teachers can allow students to view their own plagiarism reports on individual activities. This is the global switch — it must be enabled here before it can be turned on per activity.';
$string['apierror'] = 'Originality API error: {$a}';
$string['apisettings'] = 'API connection settings';
$string['apiurl'] = 'API endpoint URL';
$string['apiurl_help'] = 'The base URL of the plagiarism service API provided by UNIwise (e.g. https://api.example.com). Token and document endpoints are derived automatically.';
$string['cachedef_accesstoken'] = 'OAuth2 access tokens for the UNIwise Originality API';
$string['clear'] = 'Clear';
$string['clientid'] = 'Client ID';
$string['clientid_help'] = 'The OAuth2 client ID provided by the plagiarism service.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_help'] = 'The OAuth2 client secret provided by the plagiarism service.';
$string['enable_mod_assign'] = 'Enable for assignments';
$string['enable_mod_forum'] = 'Enable for forums';
$string['enable_mod_quiz'] = 'Enable for quizzes (essay questions)';
$string['enable_mod_workshop'] = 'Enable for workshops';
$string['externalprocessingfailed'] = 'External processing failed';
$string['failedcol_action'] = 'Action';
$string['failedcol_activity'] = 'Activity';
$string['failedcol_attempts'] = 'Attempts';
$string['failedcol_error'] = 'Error';
$string['failedcol_externalid'] = 'External ID';
$string['failedcol_filename'] = 'File';
$string['failedcol_id'] = 'ID';
$string['failedcol_time'] = 'Last attempt';
$string['failedcol_user'] = 'User';
$string['faileddeletions'] = 'Failed deletions';
$string['failedsubmissions'] = 'Failed submissions';
$string['failedtasks'] = 'Failed tasks';
$string['index_documents'] = 'Index documents';
$string['index_documents_help'] = 'When enabled, submitted documents are indexed by the plagiarism service so that they can be matched against future submissions. When disabled, documents are only analysed and not stored in the reference database.';
$string['indexsettings'] = 'Indexing';
$string['missingconfig'] = 'Originality plugin is not fully configured. Please set API URL, client ID and secret.';
$string['nofaileddeletions'] = 'No failed deletions.';
$string['nofailedsubmissions'] = 'No failed submissions.';
$string['originality_enable'] = 'Enable Originality for this activity';
$string['pluginname'] = 'UNIwise Originality plagiarism plugin';
$string['pluginsettings'] = 'Settings';
$string['privacy:metadata:plagiarism_uniwise_client'] = 'To check submissions for plagiarism, data is sent to the external UNIwise Originality service.';
$string['privacy:metadata:plagiarism_uniwise_client:courseid'] = 'The ID of the course the submission belongs to.';
$string['privacy:metadata:plagiarism_uniwise_client:coursemoduleid'] = 'The ID of the course module the submission belongs to.';
$string['privacy:metadata:plagiarism_uniwise_client:filename'] = 'The name of the submitted file.';
$string['privacy:metadata:plagiarism_uniwise_client:submission_content'] = 'The content of the submitted file or online text.';
$string['privacy:metadata:plagiarism_uniwise_client:userid'] = 'The Moodle ID of the user who made the submission.';
$string['privacy:metadata:plagiarism_uniwise_files'] = 'Information about submissions sent to the UNIwise Originality service.';
$string['privacy:metadata:plagiarism_uniwise_files:cm'] = 'The ID of the course module the submission belongs to.';
$string['privacy:metadata:plagiarism_uniwise_files:errorresponse'] = 'The error returned by the service, if the submission failed.';
$string['privacy:metadata:plagiarism_uniwise_files:externalid'] = 'The ID of the document in the UNIwise Originality service.';
$string['privacy:metadata:plagiarism_uniwise_files:filename'] = 'The name of the submitted file.';
$string['privacy:metadata:plagiarism_uniwise_files:identifier'] = 'The content hash of the submitted file.';
$string['privacy:metadata:plagiarism_uniwise_files:reporturl'] = 'The link to the similarity report.';
$string['privacy:metadata:plagiarism_uniwise_files:score'] = 'The similarity score of the submission.';
$string['privacy:metadata:plagiarism_uniwise_files:status'] = 'The processing status of the submission.';
$string['privacy:metadata:plagiarism_uniwise_files:timecreated'] = 'The time the submission was queued.';
$string['privacy:metadata:plagiarism_uniwise_files:timemodified'] = 'The time the record was last updated.';
$string['privacy:metadata:plagiarism_uniwise_files:userid'] = 'The ID of the user who made the submission.';
$string['report_fetch_error'] = 'The plagiarism report could not be loaded. Please try again later.';
$string['report_not_available'] = 'The plagiarism report is not yet available. Please try again later.';
$string['reportsettings'] = 'Report visibility';
$string['retry'] = 'Retry';
$string['retryall'] = 'Retry all';
$string['retryallqueued'] = 'All failed tasks have been re-queued for retry.';
$string['retryqueued'] = 'Task has been re-queued for retry.';
$string['savedconfigsuccess'] = 'Plagiarism settings saved';
$string['searchbyidorfile'] = 'Search by ID, user ID, CM ID, or filename...';
$string['similarity'] = 'Similarity: {$a}%';
$string['status'] = 'Status: {$a}';
$string['status_complete'] = 'Complete';
$string['status_delete_failed'] = 'Delete failed';
$string['status_error'] = 'Error';
$string['status_pending'] = 'Pending';
$string['status_submitted'] = 'Submitted';
$string['studentdisclosure'] = 'Student disclosure';
$string['studentdisclosure_help'] = 'This text will be displayed to all students on the file upload page.';
$string['studentdisclosuredefault'] = 'All files uploaded will be submitted to a plagiarism detection service';
$string['submit_on'] = 'Submit to Originality';
$string['submit_on_help'] = 'Choose when files are sent to the plagiarism service. "On upload" sends immediately when the student submits. "On marking" waits until a teacher grades the submission.';
$string['submit_on_marking'] = 'On student marking';
$string['submit_on_upload'] = 'On submission upload';
$string['submittask'] = 'Submit files to plagiarism service';
$string['timingsettings'] = 'Submission timing';
$string['uniwise:manage'] = 'Manage the UNIwise Originality plagiarism plugin';
$string['uniwise:viewreport'] = 'View plagiarism reports for other users';
$string['unknownuser'] = 'Unknown user';
$string['useoriginality'] = 'Enable originality';
$string['viewreport'] = 'View full report';
