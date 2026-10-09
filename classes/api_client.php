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
 * API client for the external originality plagiarism service.
 *
 * Handles OAuth2 client-credentials token retrieval and file submission.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

/**
 * Client for communicating with the external plagiarism checking service.
 */
class api_client {
    /** @var string Base URL of the external API. */
    private string $apiurl;

    /** @var string OAuth2 client ID. */
    private string $clientid;

    /** @var string OAuth2 client secret. */
    private string $clientsecret;

    /** @var string|null Cached access token. */
    private ?string $accesstoken = null;

    /** @var int Token expiry timestamp. */
    private int $tokenexpiry = 0;

    /**
     * Create the API client from plugin config.
     *
     * @return self
     * @throws \moodle_exception If required config is missing.
     */
    public static function create(): self {
        $config = get_config('plagiarism_uniwise');
        $apiurl = $config->originality_api_url ?? '';
        $clientid = $config->originality_client_id ?? '';
        $clientsecret = $config->originality_client_secret ?? '';

        if (empty($apiurl) || empty($clientid) || empty($clientsecret)) {
            throw new \moodle_exception('missingconfig', 'plagiarism_uniwise');
        }

        return new self($apiurl, $clientid, $clientsecret);
    }

    /**
     * Constructor.
     *
     * @param string $apiurl Base URL of the API.
     * @param string $clientid OAuth2 client ID.
     * @param string $clientsecret OAuth2 client secret.
     */
    public function __construct(string $apiurl, string $clientid, string $clientsecret) {
        // Normalize API URL: strip trailing slash.
        $this->apiurl = rtrim($apiurl, '/');
        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
    }

    /**
     * Obtain an access token using the OAuth2 client-credentials grant.
     *
     * Tokens are kept in the application cache and reused across requests
     * until they expire (with a 30-second safety margin).
     *
     * @return string The bearer access token.
     * @throws \moodle_exception On authentication failure.
     */
    public function get_access_token(): string {
        if ($this->accesstoken !== null && time() < ($this->tokenexpiry - 30)) {
            return $this->accesstoken;
        }

        $cache = \cache::make('plagiarism_uniwise', 'accesstoken');
        $cachekey = $this->get_token_cache_key();
        $cached = $cache->get($cachekey);
        if (!empty($cached['token']) && time() < ((int) $cached['expiry'] - 30)) {
            $this->accesstoken = $cached['token'];
            $this->tokenexpiry = (int) $cached['expiry'];
            return $this->accesstoken;
        }

        $postfields = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
        ], '', '&', PHP_QUERY_RFC1738);

        [$httpcode, $response] = $this->send(
            'POST',
            $this->apiurl . '/v1/oauth/token',
            $postfields,
            ['Content-Type: application/x-www-form-urlencoded'],
            false
        );

        $data = json_decode($response, true);

        if ($httpcode !== 200 || empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? $data['message'] ?? $response;
            throw new \moodle_exception('apierror', 'plagiarism_uniwise', '', $error);
        }

        $this->accesstoken = $data['access_token'];
        $this->tokenexpiry = time() + (int) ($data['expires_in'] ?? 3600);
        $cache->set($cachekey, ['token' => $this->accesstoken, 'expiry' => $this->tokenexpiry]);

        return $this->accesstoken;
    }

    /**
     * Cache key for the access token, so changing the endpoint or client ID uses a fresh token.
     *
     * @return string
     */
    private function get_token_cache_key(): string {
        return sha1($this->apiurl . '|' . $this->clientid);
    }

    /**
     * Send an HTTP request using Moodle's curl wrapper, which applies the site's proxy and security settings.
     *
     * @param string $method GET, POST or DELETE.
     * @param string $url The full request URL.
     * @param array|string $params POST body (array for multipart, string for raw).
     * @param array $headers Additional request headers.
     * @param bool $auth Whether to send the bearer token.
     * @return array [int $httpcode, string $body]
     * @throws \moodle_exception On transport failure or blocked URL.
     */
    private function send(string $method, string $url, $params = '', array $headers = [], bool $auth = true): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $headers[] = 'Accept: application/json';
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->get_access_token();
        }

        $curl = new \curl();
        $curl->setHeader($headers);

        if ($method === 'GET') {
            $response = $curl->get($url);
        } else if ($method === 'DELETE') {
            $response = $curl->delete($url);
        } else {
            $response = $curl->post($url, $params);
        }

        if (!empty($curl->error)) {
            throw new \moodle_exception('apierror', 'plagiarism_uniwise', '', 'cURL error: ' . $curl->error);
        }

        return [(int) ($curl->get_info()['http_code'] ?? 0), (string) $response];
    }

    /**
     * Submit a file to the external plagiarism service.
     *
     * @param \stored_file $file The Moodle stored file to submit.
     * @param int $cmid The course module ID for context.
     * @param int $userid The user who submitted the file.
     * @param bool $index Whether the document should be indexed by the service.
     * @return array The decoded JSON response from the service, expected to contain at least 'id'.
     * @throws \moodle_exception On submission failure.
     */
    public function submit_file(\stored_file $file, int $cmid, int $userid, bool $index = false): array {
        $tmppath = make_request_directory() . '/' . $file->get_filename();
        $file->copy_content_to($tmppath);

        try {
            return $this->submit_document(
                new \CURLFile($tmppath, $file->get_mimetype(), $file->get_filename()),
                $cmid,
                $userid,
                $index
            );
        } finally {
            @unlink($tmppath);
        }
    }

    /**
     * Submit text content to the external plagiarism service.
     *
     * @param string $content The text content to check.
     * @param int $cmid The course module ID.
     * @param int $userid The user who submitted the content.
     * @param bool $index Whether the document should be indexed by the service.
     * @return array The decoded JSON response.
     * @throws \moodle_exception On submission failure.
     */
    public function submit_text(string $content, int $cmid, int $userid, bool $index = false): array {
        $tmppath = make_request_directory() . '/onlinetext_' . $userid . '_' . $cmid . '.txt';
        file_put_contents($tmppath, $content);

        try {
            return $this->submit_document(new \CURLFile($tmppath, 'text/plain', 'onlinetext.txt'), $cmid, $userid, $index);
        } finally {
            @unlink($tmppath);
        }
    }

    /**
     * Upload a document to the external service as multipart form data.
     *
     * @param \CURLFile $upload The file to upload.
     * @param int $cmid The course module ID.
     * @param int $userid The submitting user.
     * @param bool $index Whether the document should be indexed by the service.
     * @return array The decoded JSON response.
     * @throws \moodle_exception On submission failure.
     */
    private function submit_document(\CURLFile $upload, int $cmid, int $userid, bool $index): array {
        $cm = get_coursemodule_from_id('', $cmid);
        $courseid = $cm ? (int) $cm->course : 0;

        $context = json_encode([
            'moodle_course_id' => (string) $courseid,
            'moodle_cm_id'     => (string) $cmid,
            'moodle_user_id'   => (string) $userid,
        ]);

        [$httpcode, $response] = $this->send('POST', $this->apiurl . '/v1/documents', [
            'file'    => $upload,
            'index'   => $index ? 'true' : 'false',
            'analyze' => 'true',
            'context' => $context,
        ]);

        return $this->decode_response($httpcode, $response);
    }

    /**
     * Decode a JSON API response, throwing on non-2xx status or invalid JSON.
     *
     * @param int $httpcode HTTP status code.
     * @param string $response Raw response body.
     * @return array
     * @throws \moodle_exception
     */
    private function decode_response(int $httpcode, string $response): array {
        $data = json_decode($response, true);

        if ($httpcode < 200 || $httpcode >= 300 || !is_array($data)) {
            $error = $this->extract_api_error(is_array($data) ? $data : null, $response);
            throw new \moodle_exception('apierror', 'plagiarism_uniwise', '', $error);
        }

        return $data;
    }

    /**
     * Search documents by context filter (e.g. course module ID).
     *
     * Returns all documents matching the given context key/value pair.
     *
     * @param string $contextkey The context key to filter by (e.g. 'moodle_cm_id').
     * @param string $contextvalue The value to match.
     * @return array The decoded JSON response (array of documents).
     * @throws \moodle_exception On request failure.
     */
    public function search_documents(string $contextkey, string $contextvalue): array {
        $body = json_encode([
            'context' => [
                $contextkey => $contextvalue,
            ],
        ]);

        [$httpcode, $response] = $this->send(
            'POST',
            $this->apiurl . '/v1/documents/search',
            $body,
            ['Content-Type: application/json']
        );

        return $this->decode_response($httpcode, $response);
    }

    /**
     * Poll the external service for the status/result of a submission.
     *
     * @param string $externalid The external submission ID.
     * @return array The decoded JSON response with status, score, report_url, etc.
     * @throws \moodle_exception On request failure.
     */
    public function get_submission_status(string $externalid): array {
        [$httpcode, $response] = $this->send('GET', $this->apiurl . '/v1/documents/' . urlencode($externalid));

        return $this->decode_response($httpcode, $response);
    }

    /**
     * Delete a document from the external plagiarism service.
     *
     * @param string $externalid The external document ID.
     * @throws \moodle_exception On request failure.
     */
    public function delete_document(string $externalid): void {
        [$httpcode, $response] = $this->send('DELETE', $this->apiurl . '/v1/documents/' . urlencode($externalid));

        // 200, 204, 404 are all acceptable (404 means already gone).
        if ($httpcode >= 300 && $httpcode !== 404) {
            $data = json_decode($response, true);
            $error = $this->extract_api_error(is_array($data) ? $data : null, $response);
            throw new \moodle_exception('apierror', 'plagiarism_uniwise', '', $error);
        }
    }

    /**
     * Extract a human-readable error message from a UNIwise Originality API error response.
     *
     * @param array|null $data Decoded JSON response, if available.
     * @param string $rawresponse Raw response body as fallback.
     * @return string
     */
    private function extract_api_error(?array $data, string $rawresponse): string {
        if (empty($data)) {
            return $rawresponse;
        }

        $parts = [];
        if (!empty($data['title'])) {
            $parts[] = $data['title'];
        }
        if (!empty($data['detail'])) {
            $parts[] = $data['detail'];
        }
        if (!empty($data['errorDetails']) && is_array($data['errorDetails'])) {
            foreach ($data['errorDetails'] as $detail) {
                $msg = $detail['message'] ?? '';
                $loc = $detail['location'] ?? '';
                $val = isset($detail['value']) ? json_encode($detail['value']) : '';
                $parts[] = trim("$loc: $msg ($val)");
            }
        }

        return !empty($parts) ? implode(' | ', $parts) : $rawresponse;
    }
}
