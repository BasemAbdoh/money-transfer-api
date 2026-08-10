<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public static function log(string $action, $entity = null, $old = null, $new = null): void
    {
        try {
            AuditLog::create([
                'user_id'     => auth()->id(),
                'action'      => $action,
                'entity_type' => $entity ? (is_string($entity) ? $entity : get_class($entity)) : null,
                'entity_id'   => is_object($entity) && isset($entity->id) ? $entity->id : null,
                'old_values'  => $old,
                'new_values'  => $new,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AuditLog Error: ' . $e->getMessage());
        }
    }
}
