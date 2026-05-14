<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST admin/upload      (image upload — Cloudinary)
 *   POST admin/upload-pdf  (PDF upload — Cloudinary)
 *
 * We only test validation error paths here. Actual Cloudinary uploads are
 * skipped because no CLOUDINARY_URL is set in the test environment.
 *
 * FeatureTestTrait does not support multipart/form-data natively, so we
 * test the "no file" path by posting without a file field, which the
 * controllers detect via $file->isValid() returning false.
 */
class UploadTest extends FeatureTestCase
{
    // ── Auth guards ───────────────────────────────────────────────────

    public function test_upload_requires_auth(): void
    {
        $result = $this->post('admin/upload', []);
        $result->assertStatus(401);
    }

    public function test_upload_pdf_requires_auth(): void
    {
        $result = $this->post('admin/upload-pdf', []);
        $result->assertStatus(401);
    }

    // ── POST admin/upload — no file ───────────────────────────────────

    public function test_upload_returns_422_when_no_file_provided(): void
    {
        // Posting JSON body with no multipart file — getFile('file') returns null
        $result = $this->withAdmin()->post('admin/upload', []);
        $result->assertStatus(422);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_upload_422_error_message_is_descriptive(): void
    {
        $result = $this->withAdmin()->post('admin/upload', []);
        $body   = $this->json($result);

        // Error should mention "file" in some form
        $this->assertStringContainsString('file', strtolower($body['error']));
    }

    // ── POST admin/upload-pdf — no file ──────────────────────────────

    public function test_upload_pdf_returns_422_when_no_file_provided(): void
    {
        $result = $this->withAdmin()->post('admin/upload-pdf', []);
        $result->assertStatus(422);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_upload_pdf_422_error_message_is_descriptive(): void
    {
        $result = $this->withAdmin()->post('admin/upload-pdf', []);
        $body   = $this->json($result);

        $this->assertStringContainsString('file', strtolower($body['error']));
    }

    // ── Response structure ────────────────────────────────────────────

    public function test_upload_422_response_has_error_key_not_ok(): void
    {
        $result = $this->withAdmin()->post('admin/upload', []);
        $body   = $this->json($result);

        $this->assertArrayHasKey('error', $body);
        $this->assertArrayNotHasKey('ok', $body);
        $this->assertArrayNotHasKey('url', $body);
    }

    public function test_upload_pdf_422_response_has_error_key_not_ok(): void
    {
        $result = $this->withAdmin()->post('admin/upload-pdf', []);
        $body   = $this->json($result);

        $this->assertArrayHasKey('error', $body);
        $this->assertArrayNotHasKey('ok', $body);
        $this->assertArrayNotHasKey('url', $body);
    }
}
