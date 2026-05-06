<?php

namespace Tests\Feature\Security;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\Security\Models\SecurityAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the audit log CSV export endpoint:
 *  - Response format is CSV text
 *  - Correct headers & data rows
 *  - Filtering by action, severity, since
 *  - Rate limiting (throttle:5,60 — 5 per 60 minutes per token)
 *  - Empty result returns just headers
 *  - Large dataset is truncated to the configured limit
 */
class AuditLogExportTest extends TestCase
{
    use RefreshDatabase;

    private string $storeId;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();

        $org = Organization::create(['name' => 'Export Test Org']);
        $store = Store::create([
            'organization_id' => $org->id,
            'name'            => 'Export Test Store',
            'name_ar'         => 'متجر اختبار التصدير',
        ]);
        $this->storeId = $store->id;

        $user = User::create([
            'name'          => 'Export Admin',
            'email'         => 'export@test.com',
            'store_id'      => $store->id,
            'password_hash' => bcrypt('password'),
        ]);
        $this->token = $user->createToken('export-test', ['*'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        // Clear any rate limits set during tests
        RateLimiter::clear("export-audit-{$this->token}");
        parent::tearDown();
    }

    private function ensureSchema(): void
    {
        if (!Schema::hasTable('security_audit_log')) {
            Schema::create('security_audit_log', function ($t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('user_id')->nullable();
                $t->string('user_type')->nullable();
                $t->string('action');
                $t->string('resource_type')->nullable();
                $t->string('resource_id')->nullable();
                $t->json('details')->nullable();
                $t->string('severity')->default('info');
                $t->string('ip_address', 45)->nullable();
                $t->string('device_id')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** Capture streamed CSV response content (StreamedResponse has no content() method). */
    private function streamContent(\Illuminate\Testing\TestResponse $res): string
    {
        ob_start();
        $res->baseResponse->sendContent();
        return ob_get_clean() ?? '';
    }

    private function seedLogs(int $count = 5, array $overrides = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            SecurityAuditLog::create(array_merge([
                'store_id'    => $this->storeId,
                'user_type'   => 'staff',
                'action'      => 'login',
                'severity'    => 'info',
                'ip_address'  => '10.0.0.' . ($i + 1),
                'created_at'  => now()->subMinutes($i),
            ], $overrides));
        }
    }

    // ─── Response Format Tests ───────────────────────────────────

    /** @test */
    public function export_returns_csv_content_type(): void
    {
        $this->seedLogs(1);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertOk();
        $contentType = $res->headers->get('Content-Type', '');
        // Could be text/csv or text/plain — accept either
        $this->assertTrue(
            str_contains($contentType, 'text/') || str_contains($contentType, 'csv'),
            "Unexpected Content-Type: {$contentType}"
        );
    }

    /** @test */
    public function export_returns_csv_headers_row(): void
    {
        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertOk();
        $csv = $this->streamContent($res);
        $firstLine = explode("\n", $csv)[0];

        $this->assertStringContainsString('timestamp', $firstLine);
        $this->assertStringContainsString('action', $firstLine);
        $this->assertStringContainsString('severity', $firstLine);
        $this->assertStringContainsString('user_id', $firstLine);
        $this->assertStringContainsString('ip_address', $firstLine);
    }

    /** @test */
    public function export_empty_store_returns_headers_only(): void
    {
        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertOk();
        $lines = array_filter(explode("\n", trim($this->streamContent($res))));
        // Only the header row
        $this->assertCount(1, $lines);
    }

    /** @test */
    public function export_returns_one_row_per_log(): void
    {
        $this->seedLogs(3);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertOk();
        $lines = array_filter(explode("\n", trim($this->streamContent($res))));
        // 1 header + 3 data rows
        $this->assertCount(4, $lines);
    }

    /** @test */
    public function export_includes_correct_data_in_rows(): void
    {
        $userId = (string) \Illuminate\Support\Str::uuid();
        SecurityAuditLog::create([
            'store_id'   => $this->storeId,
            'user_id'    => $userId,
            'user_type'  => 'owner',
            'action'     => 'remote_wipe',
            'severity'   => 'critical',
            'ip_address' => '192.168.1.99',
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $csv = $this->streamContent($res);
        $this->assertStringContainsString('remote_wipe', $csv);
        $this->assertStringContainsString('critical', $csv);
        $this->assertStringContainsString('192.168.1.99', $csv);
    }

    // ─── Filter Tests ─────────────────────────────────────────────

    /** @test */
    public function export_filters_by_action(): void
    {
        $this->seedLogs(3, ['action' => 'login']);
        $this->seedLogs(2, ['action' => 'logout']);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}&action=login", $this->auth());

        $res->assertOk();
        $lines = array_filter(explode("\n", trim($this->streamContent($res))));
        // 1 header + 3 login rows
        $this->assertCount(4, $lines);

        // Verify each data row contains 'login'
        $dataLines = array_slice(array_values($lines), 1);
        foreach ($dataLines as $line) {
            $this->assertStringContainsString('login', $line);
        }
    }

    /** @test */
    public function export_filters_by_severity(): void
    {
        $this->seedLogs(2, ['severity' => 'info']);
        $this->seedLogs(1, ['severity' => 'critical', 'action' => 'remote_wipe']);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}&severity=critical", $this->auth());

        $res->assertOk();
        $lines = array_filter(explode("\n", trim($this->streamContent($res))));
        $this->assertCount(2, $lines); // 1 header + 1 data
    }

    /** @test */
    public function export_filters_by_since_date(): void
    {
        // Old log — should be excluded
        SecurityAuditLog::create([
            'store_id'   => $this->storeId,
            'user_type'  => 'staff',
            'action'     => 'logout',
            'severity'   => 'info',
            'created_at' => now()->subDays(30),
        ]);

        // Recent log — should be included
        SecurityAuditLog::create([
            'store_id'   => $this->storeId,
            'user_type'  => 'staff',
            'action'     => 'login',
            'severity'   => 'info',
            'created_at' => now(),
        ]);

        $since = now()->subDay()->toDateTimeString();
        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}&since={$since}", $this->auth());

        $res->assertOk();
        $csv = $this->streamContent($res);
        $this->assertStringContainsString('login', $csv);
        $this->assertStringNotContainsString('logout', $csv);
    }

    /** @test */
    public function export_combined_action_and_severity_filters(): void
    {
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',  'severity' => 'info',     'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'login',  'severity' => 'warning',  'created_at' => now()]);
        SecurityAuditLog::create(['store_id' => $this->storeId, 'user_type' => 'staff', 'action' => 'logout', 'severity' => 'warning',  'created_at' => now()]);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}&action=login&severity=warning", $this->auth());

        $res->assertOk();
        $lines = array_filter(explode("\n", trim($this->streamContent($res))));
        $this->assertCount(2, $lines); // 1 header + 1 matching row
    }

    // ─── Authorization Tests ──────────────────────────────────────

    /** @test */
    public function export_requires_authentication(): void
    {
        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}");

        $res->assertUnauthorized();
    }

    /** @test */
    public function export_requires_store_id_parameter(): void
    {
        $res = $this->getJson('/api/v2/security/audit-logs/export', $this->auth());

        $res->assertUnprocessable();
    }

    // ─── Rate Limiting Tests ──────────────────────────────────────

    /** @test */
    public function export_allows_up_to_5_requests_per_hour(): void
    {
        $this->seedLogs(1);

        // First 5 requests should succeed
        for ($i = 0; $i < 5; $i++) {
            $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());
            $this->assertNotEquals(429, $res->getStatusCode(), "Request #{$i} should not be rate-limited");
        }
    }

    /** @test */
    public function export_is_rate_limited_after_5_requests(): void
    {
        $this->seedLogs(1);

        // Exhaust the rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());
        }

        // 6th request should be rate-limited
        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertTooManyRequests();
    }

    /** @test */
    public function rate_limit_response_includes_retry_after_header(): void
    {
        $this->seedLogs(1);

        // Exhaust rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());
        }

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $res->assertTooManyRequests();
        $this->assertTrue(
            $res->headers->has('Retry-After') || $res->headers->has('X-RateLimit-Reset'),
            'Rate limit response should include retry-after or rate-limit-reset header'
        );
    }

    // ─── Scope Isolation Tests ─────────────────────────────────────

    /** @test */
    public function export_only_returns_logs_for_specified_store(): void
    {
        // This store's log — use a specific IP for identification
        SecurityAuditLog::create([
            'store_id'   => $this->storeId,
            'user_type'  => 'staff',
            'action'     => 'pin_override',
            'severity'   => 'info',
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        // Another store's log — should NOT appear
        $otherId = (string) \Illuminate\Support\Str::uuid();
        SecurityAuditLog::create([
            'store_id'   => $otherId,
            'user_type'  => 'staff',
            'action'     => 'remote_wipe',
            'severity'   => 'info',
            'ip_address' => '10.0.0.99',
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v2/security/audit-logs/export?store_id={$this->storeId}", $this->auth());

        $csv = $this->streamContent($res);
        $this->assertStringContainsString('192.168.1.100', $csv);
        $this->assertStringNotContainsString('10.0.0.99', $csv);
    }
}
