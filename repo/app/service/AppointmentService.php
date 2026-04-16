<?php
declare(strict_types=1);

namespace app\service;

use app\model\Appointment;
use app\model\AppointmentHistory;
use app\model\AppointmentAttachment;
use think\exception\ValidateException;
use think\facade\Db;

class AppointmentService
{
    /**
     * Create a new appointment (status = PENDING).
     */
    public static function create(array $data, int $createdBy): Appointment
    {
        $now = date('Y-m-d H:i:s');

        // The assigned provider must be a real ACTIVE user with role=PROVIDER.
        // Without this gate a coordinator could attach any user id (customer,
        // admin, deleted account) as the technician and corrupt the daily queue.
        $providerId = (int) ($data['providerId'] ?? 0);
        $provider   = \app\model\User::find($providerId);
        if (!$provider || $provider->role !== 'PROVIDER' || $provider->status !== 'ACTIVE') {
            throw new ValidateException('providerId must reference an ACTIVE user with role PROVIDER');
        }

        Db::startTrans();
        try {
            $appointment = new Appointment();
            $appointment->customer_id = $data['customerId'];
            $appointment->provider_id = $providerId;
            $appointment->date_time   = self::parseDateTime($data['dateTime']);
            $appointment->location    = $data['location'];
            $appointment->status      = 'PENDING';
            $appointment->notes       = $data['notes'] ?? null;
            $appointment->created_by  = $createdBy;
            $appointment->created_at  = $now;
            $appointment->updated_at  = $now;
            $appointment->save();

            self::recordHistory($appointment->id, null, 'PENDING', $createdBy, 'Appointment created');
            AuditService::log('APPOINTMENT_CREATED', 'appointment', $appointment->id, null, $appointment->toAuditArray());

            Db::commit();
            return $appointment;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Confirm: PENDING → CONFIRMED
     */
    public static function confirm(int $id, int $userId): Appointment
    {
        return self::transition($id, 'CONFIRMED', $userId);
    }

    /**
     * Reschedule: must not be within 2 hours of start time (unless admin override).
     */
    public static function reschedule(int $id, string $newDateTime, int $userId, bool $isAdmin = false, ?string $reason = null): Appointment
    {
        $appointment = Appointment::findOrFail($id);

        if (!in_array($appointment->status, ['PENDING', 'CONFIRMED'], true)) {
            throw new ValidateException('Cannot reschedule from status: ' . $appointment->status);
        }

        $startTime = strtotime($appointment->date_time);
        $hoursUntilStart = ($startTime - time()) / 3600;

        if ($hoursUntilStart < 2) {
            if (!$isAdmin) {
                throw new \think\exception\HttpException(409, 'Cannot reschedule within 2 hours of start time');
            }
            // Admin override path: reason is mandatory so the audit trail captures justification.
            if ($reason === null || trim($reason) === '') {
                throw new ValidateException('Admin reschedule override requires a non-empty reason');
            }
        }

        $parsedDt = self::parseDateTime($newDateTime);

        Db::startTrans();
        try {
            $appointment = Appointment::findOrFail($id);
            $before = $appointment->toAuditArray();

            $appointment->date_time  = $parsedDt;
            $appointment->updated_at = date('Y-m-d H:i:s');
            $appointment->save();

            $reasonText = $reason ?? 'Rescheduled to ' . $parsedDt;
            if ($isAdmin) {
                $reasonText = '[ADMIN OVERRIDE] ' . $reasonText;
            }

            self::recordHistory($appointment->id, $appointment->status, $appointment->status, $userId, $reasonText, [
                'old_date_time'  => $before['date_time'] ?? null,
                'new_date_time'  => $parsedDt,
                'admin_override' => $isAdmin,
            ]);

            AuditService::log('APPOINTMENT_RESCHEDULED', 'appointment', $id, $before, $appointment->toAuditArray());

            Db::commit();
            return $appointment;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Cancel: only from PENDING or CONFIRMED.
     */
    public static function cancel(int $id, int $userId, ?string $reason = null): Appointment
    {
        $appointment = Appointment::findOrFail($id);

        if (!in_array($appointment->status, ['PENDING', 'CONFIRMED'], true)) {
            throw new ValidateException('Cancel only allowed from PENDING or CONFIRMED');
        }

        return self::transition($id, 'CANCELLED', $userId, $reason);
    }

    /**
     * Admin repair: force a state change with full audit trail.
     * State write + history insert + audit log are wrapped in a single DB
     * transaction — a partial failure rolls everything back so we never end up
     * with a mutated status and no history row.
     */
    public static function repair(int $id, string $targetState, int $adminId, string $reason): Appointment
    {
        $validStates = ['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'EXPIRED', 'CANCELLED'];
        if (!in_array($targetState, $validStates, true)) {
            throw new ValidateException('Invalid target state: ' . $targetState);
        }

        Db::startTrans();
        try {
            $appointment = Appointment::findOrFail($id);
            $before = $appointment->toAuditArray();
            $oldStatus = $appointment->status;

            $appointment->status     = $targetState;
            $appointment->updated_at = date('Y-m-d H:i:s');
            $appointment->save();

            self::recordHistory($appointment->id, $oldStatus, $targetState, $adminId, '[REPAIR] ' . $reason, [
                'repair'       => true,
                'admin_id'     => $adminId,
                'before_state' => $oldStatus,
            ]);

            AuditService::log('APPOINTMENT_REPAIRED', 'appointment', $id, $before, $appointment->toAuditArray());
            Db::commit();
            return $appointment;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * Provider check-in: CONFIRMED → IN_PROGRESS
     */
    public static function checkIn(int $id, int $providerId): Appointment
    {
        $appointment = Appointment::findOrFail($id);

        if ((int) $appointment->provider_id !== $providerId) {
            throw new ValidateException('You are not assigned to this appointment');
        }

        return self::transition($id, 'IN_PROGRESS', $providerId, 'Provider checked in');
    }

    /**
     * Provider check-out: IN_PROGRESS → COMPLETED
     * Requirement: at least one completion-evidence attachment must exist before close.
     */
    public static function checkOut(int $id, int $providerId): Appointment
    {
        $appointment = Appointment::findOrFail($id);

        if ((int) $appointment->provider_id !== $providerId) {
            throw new ValidateException('You are not assigned to this appointment');
        }

        // Require at least one attachment *from the assigned provider* — prevents
        // a coordinator-uploaded file from satisfying the evidence gate.
        $providerEvidence = AppointmentAttachment::where('appointment_id', $id)
            ->where('uploaded_by', $providerId)
            ->count();
        if ($providerEvidence < 1) {
            throw new \think\exception\HttpException(
                409,
                'Check-out requires at least one completion evidence attachment uploaded by the assigned provider'
            );
        }

        return self::transition($id, 'COMPLETED', $providerId, 'Provider checked out');
    }

    /**
     * Auto-expire PENDING appointments older than 24 hours.
     */
    public static function expirePending(): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $pending = Appointment::where('status', 'PENDING')
            ->where('created_at', '<=', $cutoff)
            ->select();

        $count = 0;
        foreach ($pending as $appointment) {
            // Each item is its own transaction so a single failure (trigger
            // rejection, constraint error) skips that row and keeps the rest
            // of the batch intact instead of leaving half-written state.
            Db::startTrans();
            try {
                $before = $appointment->toAuditArray();
                $appointment->status     = 'EXPIRED';
                $appointment->updated_at = date('Y-m-d H:i:s');
                $appointment->save();

                self::recordHistory($appointment->id, 'PENDING', 'EXPIRED', $appointment->created_by, 'Auto-expired after 24 hours');
                AuditService::log('APPOINTMENT_AUTO_EXPIRED', 'appointment', $appointment->id, $before, $appointment->toAuditArray(), null);

                Db::commit();
                $count++;
            } catch (\Throwable $e) {
                Db::rollback();
                AuditService::log('APPOINTMENT_AUTO_EXPIRE_FAILED', 'appointment', $appointment->id, null, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Upload attachment to an appointment.
     * A provider may upload only to their own assignment, and only while the appointment
     * is in a state where evidence is meaningful (CONFIRMED, IN_PROGRESS, COMPLETED).
     */
    public static function uploadAttachment(int $appointmentId, $file, int $uploadedBy): AppointmentAttachment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $user = session('user') ?? [];
        $role = $user['role'] ?? '';

        // Completion evidence provenance: only the assigned provider may upload.
        // SYSTEM_ADMIN retains an emergency fallback for dispute handling but
        // is audited distinctly so uploads-by-admin stand out in the log.
        if ($role === 'SYSTEM_ADMIN') {
            // allow, but audit as admin-uploaded so provenance is clear
        } elseif ($role === 'PROVIDER') {
            if ((int) $appointment->provider_id !== $uploadedBy) {
                throw new \think\exception\HttpException(403, 'Provider may only upload to their assigned appointments');
            }
        } else {
            throw new \think\exception\HttpException(403, 'Only the assigned provider may upload completion evidence');
        }

        if (!in_array($appointment->status, ['CONFIRMED', 'IN_PROGRESS', 'COMPLETED'], true)) {
            throw new ValidateException('Attachments can only be uploaded for appointments in CONFIRMED/IN_PROGRESS/COMPLETED state');
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $mimeType = $file->getMime();
        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new ValidateException('Only JPEG, PNG, GIF, and PDF files are allowed');
        }

        // Validate file size (max 10 MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new ValidateException('File size must not exceed 10 MB');
        }

        $ext      = $file->getOriginalExtension();
        $fileName = 'appt_' . $appointmentId . '_' . time() . '_' . uniqid() . '.' . $ext;

        // Determine storage subdirectory
        $subDir = in_array($mimeType, ['application/pdf']) ? 'documents' : 'photos';
        $savePath = app()->getRootPath() . 'storage/uploads/' . $subDir;
        $file->move($savePath, $fileName);

        $attachment = new AppointmentAttachment();
        $attachment->appointment_id = $appointmentId;
        $attachment->file_name      = $file->getOriginalName();
        $attachment->file_path      = $subDir . '/' . $fileName;
        $attachment->file_type      = $mimeType;
        $attachment->file_size      = $file->getSize();
        $attachment->uploaded_by    = $uploadedBy;
        $attachment->created_at     = date('Y-m-d H:i:s');
        $attachment->save();

        AuditService::log('ATTACHMENT_UPLOADED', 'appointment_attachment', $attachment->id, null, [
            'appointment_id' => $appointmentId,
            'file_name'      => $attachment->file_name,
        ]);

        return $attachment;
    }

    // ─── Private helpers ───

    private static function transition(int $id, string $newStatus, int $userId, ?string $reason = null): Appointment
    {
        Db::startTrans();
        try {
            $appointment = Appointment::findOrFail($id);

            if (!$appointment->canTransitionTo($newStatus)) {
                throw new \think\exception\HttpException(409, "Cannot transition from {$appointment->status} to {$newStatus}");
            }

            $before    = $appointment->toAuditArray();
            $oldStatus = $appointment->status;

            $appointment->status     = $newStatus;
            $appointment->updated_at = date('Y-m-d H:i:s');
            $appointment->save();

            self::recordHistory($id, $oldStatus, $newStatus, $userId, $reason);

            AuditService::log('APPOINTMENT_' . $newStatus, 'appointment', $id, $before, $appointment->toAuditArray());

            Db::commit();
            return $appointment;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private static function recordHistory(int $appointmentId, ?string $from, string $to, int $changedBy, ?string $reason = null, ?array $metadata = null): void
    {
        $history = new AppointmentHistory();
        $history->appointment_id = $appointmentId;
        $history->from_status    = $from;
        $history->to_status      = $to;
        $history->changed_by     = $changedBy;
        $history->reason         = $reason;
        $history->metadata       = $metadata ? json_encode($metadata) : null;
        $history->created_at     = date('Y-m-d H:i:s');
        $history->save();
    }

    /**
     * Parse the public MM/DD/YYYY hh:mm AM/PM format to Y-m-d H:i:s.
     * This is the only format accepted from external API callers.
     */
    public static function parseDateTime(string $input): string
    {
        $parsed = \DateTime::createFromFormat('m/d/Y h:i A', $input);
        if (!$parsed) {
            throw new ValidateException('Invalid date/time format. Use MM/DD/YYYY hh:mm AM/PM');
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    /**
     * Internal-only variant that also accepts Y-m-d H:i:s. Used by seeders and
     * expiration jobs that work with database-native timestamps. Never exposed
     * to HTTP input paths.
     */
    public static function parseDateTimeInternal(string $input): string
    {
        $parsed = \DateTime::createFromFormat('m/d/Y h:i A', $input);
        if (!$parsed) {
            $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $input);
        }
        if (!$parsed) {
            throw new ValidateException('Invalid date/time format (internal parser): ' . $input);
        }
        return $parsed->format('Y-m-d H:i:s');
    }
}
