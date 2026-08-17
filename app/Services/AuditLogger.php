<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(
        string $action,
        ?int $branchId,
        ?string $description = null,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $metadata = []
    ): void {
        try {
            if (!Schema::hasTable('audit_logs')) {
                return;
            }

            $request = request();
            DB::table('audit_logs')->insert([
                'branch_id' => $branchId,
                'user_id' => null,
                'action' => Str::limit($action, 80, ''),
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'description' => $description ? Str::limit($description, 500, '') : null,
                'before_values' => null,
                'after_values' => null,
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'route_name' => $request->route()?->getName(),
                'http_method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit logging must never break public QR ordering during mixed-version rollout.
        }
    }
}
