<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Appointment extends Model
{
    protected $table = 'pp_appointments';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id'          => 'integer',
        'customer_id' => 'integer',
        'provider_id' => 'integer',
        'created_by'  => 'integer',
    ];

    protected $hidden = ['location_encrypted'];

    /**
     * The physical `location` column is ciphertext-only; the model exposes a
     * synthetic `location` attribute that decrypts on demand and a
     * `location_hint` column that stores the first 16 characters (typically a
     * building/venue name) so list views stay useful without leaking the
     * street-level detail.
     */
    public function setLocationAttr($value): string
    {
        if (is_string($value) && $value !== '') {
            // Use __set (via property assignment) so ThinkPHP's Attribute
            // concern writes to its private `$data` store. Writing
            // `$this->data['key'] = ...` directly would go through __get,
            // return a copy of the array, and trigger PHP's "indirect
            // modification of overloaded property" error.
            $this->location_encrypted = encrypt_field($value);
            $hint = trim(preg_split('/[,;]/', $value)[0] ?? '');
            $this->location_hint = mb_substr($hint, 0, 16);
        }
        // Persist an empty string into the legacy `location` column — the real
        // value only ever lives in `location_encrypted` (ciphertext).
        return '';
    }

    public function getLocationAttr($value, $data)
    {
        if (!empty($data['location_encrypted'])) {
            try {
                return decrypt_field($data['location_encrypted']);
            } catch (\Throwable $e) {
                return '[decryption failed]';
            }
        }
        return $data['location_hint'] ?? '';
    }

    /**
     * Audit-safe array — scrubs the plaintext location and raw ciphertext so
     * AuditService::log() never persists either into `pp_audit_logs`.
     */
    public function toAuditArray(): array
    {
        $row = $this->toArray();
        unset($row['location'], $row['location_encrypted']);
        return $row;
    }

    // Valid state transitions
    const TRANSITIONS = [
        'PENDING'     => ['CONFIRMED', 'CANCELLED', 'EXPIRED'],
        'CONFIRMED'   => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['COMPLETED'],
        'COMPLETED'   => [],
        'EXPIRED'     => [],
        'CANCELLED'   => [],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed, true);
    }

    public function history()
    {
        return $this->hasMany(AppointmentHistory::class, 'appointment_id');
    }

    public function attachments()
    {
        return $this->hasMany(AppointmentAttachment::class, 'appointment_id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
