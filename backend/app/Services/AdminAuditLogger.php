<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditLogger
{
    private const SENSITIVE_KEY_PATTERN = '/password|secret|token|cookie|authorization|session|storage|path|filename|sha|hash|content/i';

    public function record(
        ?Request $request,
        ?Admin $admin,
        string $action,
        string $outcome,
        ?string $targetType = null,
        string|int|null $targetId = null,
        ?string $reason = null,
        ?array $before = null,
        ?array $after = null,
    ): AdminAuditLog {
        if (! preg_match('/^[a-z0-9_.-]{3,96}$/D', $action)) {
            throw new \InvalidArgumentException('Invalid audit action.');
        }
        if (! in_array($outcome, ['success', 'denied', 'failure'], true)) {
            throw new \InvalidArgumentException('Invalid audit outcome.');
        }

        $entry = new AdminAuditLog;
        $entry->forceFill([
            'admin_id' => $admin?->getKey(),
            'role_snapshot' => $admin?->role,
            'action' => $action,
            'target_type' => $this->bounded($targetType, 64),
            'target_id' => $this->bounded($targetId === null ? null : (string) $targetId, 64),
            'outcome' => $outcome,
            'reason' => $this->bounded($this->normalize($reason), (int) config('admin.audit.reason_max_length', 500)),
            'correlation_id' => $this->correlationId($request),
            'ip_address' => config('admin.audit.capture_ip', true) ? $this->bounded($request?->ip(), 45) : null,
            'user_agent' => config('admin.audit.capture_user_agent', true)
                ? $this->bounded($request?->userAgent(), (int) config('admin.audit.user_agent_max_length', 512))
                : null,
            'before_state' => $this->safeState($before),
            'after_state' => $this->safeState($after),
            'logged_at' => now(),
        ])->save();

        return $entry;
    }

    private function correlationId(?Request $request): string
    {
        $value = $request?->attributes->get('admin_correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function bounded(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }

    private function safeState(?array $state): ?array
    {
        if ($state === null) {
            return null;
        }

        $safe = [];
        foreach ($state as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->safeState($value) : $value;
        }

        return $safe;
    }
}
