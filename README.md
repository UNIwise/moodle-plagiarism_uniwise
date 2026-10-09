# UNIwise Originality plagiarism plugin for Moodle

A Moodle plagiarism plugin (`plagiarism_uniwise`) that integrates with the
UNIwise Originality service to check student submissions for plagiarism.
Submissions are sent to the external service in the background and similarity
scores are displayed inline alongside each file on grading and submission pages.

This plugin is for institutions that use UNIwise Originality. It sends student
submissions to the UNIwise Originality service and does nothing without valid
API credentials, which are issued to existing customers by their UNIwise
administrator.

## Requirements

- Moodle 4.1 or later
- PHP 8.0 or later
- UNIwise Originality API credentials (endpoint URL, OAuth2 client ID and secret)
- Outbound HTTPS access from the Moodle server to the API endpoint. Requests use
  Moodle's HTTP client, so the site's proxy settings and
  "cURL blocked hosts list" (Site administration > General > Security >
  HTTP security) apply.

## Features

- Automatic submission of student files and online text to the Originality
  service via background processing (adhoc tasks with exponential backoff retry).
- Inline display of similarity scores and report links on grading pages.
- Per-activity configuration: enable or disable plagiarism checking on
  individual assignments, forums, workshops, and quizzes.
- Configurable submission timing: submit on upload or defer until marking.
- Student report visibility controls at both global and per-activity level.
- Admin page for monitoring and retrying failed submissions and deletions.
- Automatic cleanup: when a submission is removed, the corresponding document
  is deleted from the external service.

## Installation

1. Copy the plugin into your Moodle installation so that it lives at
   `plagiarism/uniwise/` (on Moodle 5.1+ this is `public/plagiarism/uniwise/`).
   The folder must be named `uniwise`.

2. Log in as a site administrator and visit the notifications page
   (Site administration > Notifications) to trigger the database upgrade,
   or run the CLI upgrade:

   ```sh
   php admin/cli/upgrade.php
   ```

## Configuration

1. Navigate to Site administration > Advanced features and ensure
   "Enable plagiarism plugins" is checked.
2. Go to Site administration > Plugins > Plagiarism > UNIwise Originality
   plagiarism plugin.
3. Check "Enable originality" to activate the plugin.
4. Enter the API connection details issued by your UNIwise administrator:
   - **API endpoint URL**: the base URL of the Originality API, for example
     `https://api.example.com`.
   - **Client ID**: the OAuth2 client ID.
   - **Client secret**: the OAuth2 client secret.
5. Under "Supported activities", enable the activity types you want to
   support (Assignments, Forums, Workshops, Quizzes).
6. Optionally configure report visibility and submission timing defaults.
7. Save changes.

## Usage

Once configured globally, plagiarism checking can be enabled on individual
activities:

1. Edit an activity (e.g. an Assignment) and expand the
   "UNIwise Originality plagiarism plugin" section.
2. Check "Enable Originality for this activity".
3. Optionally allow students to view their own reports and choose whether
   submissions are sent on upload or on marking.
4. Save the activity. Student submissions will now be sent to the Originality
   service automatically according to the chosen timing.

Similarity scores and report links appear next to each submission on the
grading page. Teachers and managers can always view reports. Students can
view their own reports only when both the global and per-activity student
report settings are enabled.

## Failed tasks

If a submission or deletion fails after all retry attempts, it appears on the
Failed Tasks tab in the plugin settings page. Administrators can retry
individual tasks or all failed tasks at once from this page.

## Scheduled task

Similarity scores are fetched live from the API whenever a grading or
submission page is viewed (batch-fetched per activity and cached for the
duration of the request). In addition, the plugin registers a scheduled task
("Submit files to plagiarism service") that runs every 5 minutes to sync
results into the local database for records that have been submitted but not
yet completed. The task can be managed from
Site administration > Server > Scheduled tasks.

## Capabilities

| Capability | Description |
| --- | --- |
| `plagiarism/uniwise:viewreport` | View plagiarism reports for other users' submissions. Granted by default to editing teachers and managers. |
| `plagiarism/uniwise:manage` | Manage the plagiarism plugin settings. |

## Privacy

The plugin sends the following data to the external UNIwise Originality
service: the submitted file or online text, its filename, and the Moodle
user, course and course module IDs. Locally it stores the submission status,
similarity score, report link and external document ID. This is declared
through the Moodle Privacy API, and data export and deletion requests are
supported (deletions are also propagated to the external service).

## Bug reports

Please report issues on the
[GitHub issue tracker](https://github.com/UNIwise/moodle-plagiarism_uniwise/issues).

## License

This plugin is licensed under the GNU General Public License v3 or later.
See [COPYING.txt](COPYING.txt) or https://www.gnu.org/licenses/gpl-3.0.html
for details.
