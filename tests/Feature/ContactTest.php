<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST contact
 *
 * Note: RESEND_API_KEY is not set in the test environment, so any test that
 * reaches the email-sending step will receive a 503. We use this to confirm
 * validation passes before the email step.
 */
class ContactTest extends FeatureTestCase
{
    // ── Missing required fields ───────────────────────────────────────

    public function test_send_returns_400_when_all_fields_missing(): void
    {
        $result = $this->post('contact', []);
        $result->assertStatus(400);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_send_returns_400_when_name_is_missing(): void
    {
        $result = $this->post('contact', [
            'email'   => 'user@example.com',
            'message' => 'Hello there',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_when_email_is_missing(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'message' => 'Hello there',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_when_message_is_missing(): void
    {
        $result = $this->post('contact', [
            'name'  => 'Jane Doe',
            'email' => 'user@example.com',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_when_name_is_empty_string(): void
    {
        $result = $this->post('contact', [
            'name'    => '',
            'email'   => 'user@example.com',
            'message' => 'Hello',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_when_message_is_empty_string(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'user@example.com',
            'message' => '',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_when_email_is_empty_string(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => '',
            'message' => 'Hello',
        ]);
        $result->assertStatus(400);
    }

    // ── Email validation ──────────────────────────────────────────────

    public function test_send_returns_400_for_invalid_email_no_at_sign(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'notanemail',
            'message' => 'Hello there',
        ]);
        $result->assertStatus(400);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_send_returns_400_for_invalid_email_missing_domain(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'user@',
            'message' => 'Hello there',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_for_invalid_email_with_spaces(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'user @example.com',
            'message' => 'Hello there',
        ]);
        $result->assertStatus(400);
    }

    public function test_send_returns_400_for_invalid_email_plain_string(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane',
            'email'   => 'plainstring',
            'message' => 'Test',
        ]);
        $result->assertStatus(400);
    }

    // ── RESEND_API_KEY not configured → 503 ───────────────────────────

    public function test_send_returns_503_when_resend_api_key_not_set(): void
    {
        // In the test environment RESEND_API_KEY is not in .env, so getenv() returns false.
        // The controller detects this and returns 503 before attempting to send.
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'message' => 'I would like more information about your services.',
        ]);
        $result->assertStatus(503);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_send_503_response_has_descriptive_error_message(): void
    {
        $result = $this->post('contact', [
            'name'    => 'John Smith',
            'email'   => 'john@example.com',
            'message' => 'Please call me.',
        ]);

        $body = $this->json($result);
        $this->assertStringContainsString('not configured', strtolower($body['error']));
    }

    // ── Optional fields are accepted ─────────────────────────────────

    public function test_send_accepts_optional_phone_field(): void
    {
        // Validation passes (all required fields present + optional phone)
        // but fails at the RESEND_API_KEY step → 503, not 400
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'phone'   => '+27 82 123 4567',
            'message' => 'I need help.',
        ]);
        $result->assertStatus(503); // passes validation, fails at email step
    }

    public function test_send_accepts_optional_service_field(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'service' => 'Training',
            'message' => 'Enquiry about training.',
        ]);
        $result->assertStatus(503); // passes validation, fails at email step
    }

    public function test_send_accepts_all_fields_together(): void
    {
        $result = $this->post('contact', [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'phone'   => '0821234567',
            'service' => 'Compliance',
            'message' => 'Enquiry about compliance services.',
        ]);
        $result->assertStatus(503); // passes all validation
    }
}
