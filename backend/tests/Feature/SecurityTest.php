<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

/**
 * Security Feature Tests
 * Phase 1: Security Hardening Verification
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // Security Headers Tests
    // ============================================

    public function test_api_response_includes_security_headers(): void
    {
        $response = $this->get('/api/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_csp_header_contains_required_directives(): void
    {
        $response = $this->get('/api/health');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    // ============================================
    // Rate Limiting Tests
    // ============================================

    public function test_login_rate_limiting_enforced(): void
    {
        // Make 6 login attempts (limit is 5/min)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_api_rate_limit_header_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        // Rate limit headers should be present
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') ||
            $response->headers->has('X-RateLimit-Remaining')
        );
    }
}
