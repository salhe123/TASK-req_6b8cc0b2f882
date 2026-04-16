<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class User extends Model
{
    protected $table = 'pp_users';

    protected $hidden = ['password'];

    protected $type = [
        'id'                    => 'integer',
        'failed_login_attempts' => 'integer',
    ];

    protected $json = [];

    // Don't auto-write timestamps; we handle them manually
    protected $autoWriteTimestamp = false;

    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->getData('password'));
    }

    public function isLocked(): bool
    {
        return $this->status === 'LOCKED';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function incrementFailedAttempts(): void
    {
        $this->failed_login_attempts = $this->failed_login_attempts + 1;
        $now = date('Y-m-d H:i:s');
        $this->updated_at = $now;

        // Lock after 5 failed attempts
        if ($this->failed_login_attempts >= 5) {
            $this->status = 'LOCKED';
            $this->locked_at = $now;
        }

        $this->save();
    }

    public function resetFailedAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();
    }

    public function toSessionArray(): array
    {
        return [
            'id'       => $this->id,
            'username' => $this->username,
            'role'     => $this->role,
            'status'   => $this->status,
        ];
    }
}
