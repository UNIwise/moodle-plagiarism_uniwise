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
 * Unit tests for the plagiarism_uniwise API client.
 *
 * Tests for logic that does not require a live API connection:
 * URL normalization, token caching, factory validation, and error extraction.
 *
 * @package    plagiarism_uniwise
 * @copyright  2026 UNIwise
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_uniwise;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the api_client class.
 *
 * @covers \plagiarism_uniwise\api_client
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\plagiarism_uniwise\api_client::class)]
final class api_client_test extends \advanced_testcase {
    // Constructor / URL normalization.

    /**
     * Test that the constructor strips a trailing slash from the API URL.
     *
     * @covers \plagiarism_uniwise\api_client::__construct
     */
    public function test_constructor_strips_trailing_slash(): void {
        $client = new api_client('https://api.example.com/', 'id', 'secret');

        // We verify by checking the object was created without error.
        // The URL normalization is internal, so we test it indirectly
        // through the reflection API.
        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('apiurl');
        $prop->setAccessible(true);
        $this->assertEquals('https://api.example.com', $prop->getValue($client));
    }

    /**
     * Test that the constructor handles a URL without a trailing slash.
     *
     * @covers \plagiarism_uniwise\api_client::__construct
     */
    public function test_constructor_handles_url_without_trailing_slash(): void {
        $client = new api_client('https://api.example.com', 'id', 'secret');

        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('apiurl');
        $prop->setAccessible(true);
        $this->assertEquals('https://api.example.com', $prop->getValue($client));
    }

    /**
     * Test that the constructor strips multiple trailing slashes from the API URL.
     *
     * @covers \plagiarism_uniwise\api_client::__construct
     */
    public function test_constructor_strips_multiple_trailing_slashes(): void {
        $client = new api_client('https://api.example.com///', 'id', 'secret');

        $reflection = new \ReflectionClass($client);
        $prop = $reflection->getProperty('apiurl');
        $prop->setAccessible(true);
        $this->assertEquals('https://api.example.com', $prop->getValue($client));
    }

    // Factory create() - missing config.

    /**
     * Test that create() throws when the API URL is missing.
     *
     * @covers \plagiarism_uniwise\api_client::create
     */
    public function test_create_throws_when_api_url_missing(): void {
        $this->resetAfterTest();

        set_config('originality_api_url', '', 'plagiarism_uniwise');
        set_config('originality_client_id', 'testid', 'plagiarism_uniwise');
        set_config('originality_client_secret', 'testsecret', 'plagiarism_uniwise');

        $this->expectException(\moodle_exception::class);
        api_client::create();
    }

    /**
     * Test that create() throws when the client ID is missing.
     *
     * @covers \plagiarism_uniwise\api_client::create
     */
    public function test_create_throws_when_client_id_missing(): void {
        $this->resetAfterTest();

        set_config('originality_api_url', 'https://api.example.com', 'plagiarism_uniwise');
        set_config('originality_client_id', '', 'plagiarism_uniwise');
        set_config('originality_client_secret', 'testsecret', 'plagiarism_uniwise');

        $this->expectException(\moodle_exception::class);
        api_client::create();
    }

    /**
     * Test that create() throws when the client secret is missing.
     *
     * @covers \plagiarism_uniwise\api_client::create
     */
    public function test_create_throws_when_client_secret_missing(): void {
        $this->resetAfterTest();

        set_config('originality_api_url', 'https://api.example.com', 'plagiarism_uniwise');
        set_config('originality_client_id', 'testid', 'plagiarism_uniwise');
        set_config('originality_client_secret', '', 'plagiarism_uniwise');

        $this->expectException(\moodle_exception::class);
        api_client::create();
    }

    /**
     * Test that create() succeeds when all config values are present.
     *
     * @covers \plagiarism_uniwise\api_client::create
     */
    public function test_create_succeeds_with_all_config(): void {
        $this->resetAfterTest();

        set_config('originality_api_url', 'https://api.example.com', 'plagiarism_uniwise');
        set_config('originality_client_id', 'testid', 'plagiarism_uniwise');
        set_config('originality_client_secret', 'testsecret', 'plagiarism_uniwise');

        $client = api_client::create();
        $this->assertInstanceOf(api_client::class, $client);
    }

    // Get_access_token() - in-memory cache.

    /**
     * Test that get_access_token returns the cached in-memory token.
     *
     * @covers \plagiarism_uniwise\api_client::get_access_token
     */
    public function test_get_access_token_returns_cached_inmemory_token(): void {
        $this->resetAfterTest();

        $client = new api_client('https://api.example.com', 'id', 'secret');

        // Inject a cached token via reflection.
        $reflection = new \ReflectionClass($client);
        $tokenprop = $reflection->getProperty('accesstoken');
        $tokenprop->setAccessible(true);
        $tokenprop->setValue($client, 'cached_token_value');

        $expiryprop = $reflection->getProperty('tokenexpiry');
        $expiryprop->setAccessible(true);
        $expiryprop->setValue($client, time() + 3600);

        // Should return the cached token without making any HTTP request.
        $token = $client->get_access_token();
        $this->assertEquals('cached_token_value', $token);
    }

    /**
     * Test that get_access_token returns a token from the application cache.
     *
     * @covers \plagiarism_uniwise\api_client::get_access_token
     */
    public function test_get_access_token_returns_persistent_cached_token(): void {
        $this->resetAfterTest();

        \cache::make('plagiarism_uniwise', 'accesstoken')->set(
            sha1('https://api.example.com|id'),
            ['token' => 'persistent_token', 'expiry' => time() + 3600]
        );

        $client = new api_client('https://api.example.com', 'id', 'secret');

        $token = $client->get_access_token();
        $this->assertEquals('persistent_token', $token);
    }

    /**
     * Test that a cached token for a different client ID is not reused.
     *
     * @covers \plagiarism_uniwise\api_client::get_access_token
     */
    public function test_get_access_token_ignores_token_for_other_client(): void {
        $this->resetAfterTest();

        \cache::make('plagiarism_uniwise', 'accesstoken')->set(
            sha1('https://nonexistent.invalid|other'),
            ['token' => 'other_token', 'expiry' => time() + 3600]
        );

        $client = new api_client('https://nonexistent.invalid', 'id', 'secret');

        $this->expectException(\moodle_exception::class);
        $client->get_access_token();
    }

    /**
     * Test that get_access_token ignores an expired cached token.
     *
     * @covers \plagiarism_uniwise\api_client::get_access_token
     */
    public function test_get_access_token_ignores_expired_persistent_cache(): void {
        $this->resetAfterTest();

        \cache::make('plagiarism_uniwise', 'accesstoken')->set(
            sha1('https://nonexistent.invalid|id'),
            ['token' => 'expired_token', 'expiry' => time() - 100]
        );

        $client = new api_client('https://nonexistent.invalid', 'id', 'secret');

        // Should attempt a real request and fail (since URL is invalid).
        $this->expectException(\moodle_exception::class);
        $client->get_access_token();
    }

    // Extract_api_error() - tested via reflection.

    /**
     * Test extract_api_error with title and detail fields.
     *
     * @covers \plagiarism_uniwise\api_client::extract_api_error
     */
    public function test_extract_api_error_with_title_and_detail(): void {
        $client = new api_client('https://api.example.com', 'id', 'secret');
        $method = new \ReflectionMethod($client, 'extract_api_error');
        $method->setAccessible(true);

        $data = ['title' => 'Not Found', 'detail' => 'Document does not exist'];
        $result = $method->invoke($client, $data, '');

        $this->assertEquals('Not Found | Document does not exist', $result);
    }

    /**
     * Test extract_api_error with an errorDetails array.
     *
     * @covers \plagiarism_uniwise\api_client::extract_api_error
     */
    public function test_extract_api_error_with_error_details_array(): void {
        $client = new api_client('https://api.example.com', 'id', 'secret');
        $method = new \ReflectionMethod($client, 'extract_api_error');
        $method->setAccessible(true);

        $data = [
            'title' => 'Validation Error',
            'errorDetails' => [
                ['message' => 'required', 'location' => 'body.file', 'value' => null],
            ],
        ];
        $result = $method->invoke($client, $data, '');

        $this->assertStringContainsString('Validation Error', $result);
        $this->assertStringContainsString('body.file', $result);
        $this->assertStringContainsString('required', $result);
    }

    /**
     * Test extract_api_error falls back to raw response when data is null.
     *
     * @covers \plagiarism_uniwise\api_client::extract_api_error
     */
    public function test_extract_api_error_falls_back_to_raw_response(): void {
        $client = new api_client('https://api.example.com', 'id', 'secret');
        $method = new \ReflectionMethod($client, 'extract_api_error');
        $method->setAccessible(true);

        $result = $method->invoke($client, null, 'raw error body');
        $this->assertEquals('raw error body', $result);
    }

    /**
     * Test extract_api_error returns raw response when data is empty.
     *
     * @covers \plagiarism_uniwise\api_client::extract_api_error
     */
    public function test_extract_api_error_empty_data_returns_raw(): void {
        $client = new api_client('https://api.example.com', 'id', 'secret');
        $method = new \ReflectionMethod($client, 'extract_api_error');
        $method->setAccessible(true);

        $result = $method->invoke($client, [], 'fallback');
        $this->assertEquals('fallback', $result);
    }
}
