<?php
declare(strict_types=1);

namespace app\controller\appointment;

use app\BaseController;
use app\model\Appointment as AppointmentModel;
use app\service\AppointmentService;
use think\exception\ValidateException;

class Appointment extends BaseController
{
    /**
     * Check that the current user is allowed to access this specific appointment.
     * - SYSTEM_ADMIN, SERVICE_COORDINATOR: always allowed (list is already role-scoped)
     * - PROVIDER: only when assigned to the appointment
     */
    private function assertCanAccess(AppointmentModel $appt): ?\think\Response
    {
        $user = session('user');
        if (!$user) {
            return json_error('Unauthorized', 401);
        }
        $role = $user['role'];
        if ($role === 'SYSTEM_ADMIN' || $role === 'SERVICE_COORDINATOR') {
            return null;
        }
        if ($role === 'PROVIDER' && (int) $appt->provider_id === (int) $user['id']) {
            return null;
        }
        return json_error('Access denied (not owner of this appointment)', 403);
    }

    public function index()
    {
        $user       = session('user');
        $status     = $this->request->get('status', '');
        $providerId = $this->request->get('providerId', '');
        $dateFrom   = $this->request->get('dateFrom', '');
        $dateTo     = $this->request->get('dateTo', '');
        $page       = (int) $this->request->get('page', 1);
        $size       = (int) $this->request->get('size', 20);

        $query = AppointmentModel::order('date_time', 'desc');

        if ($status) {
            $query->where('status', $status);
        }
        // Providers only ever see their own queue.
        if ($user['role'] === 'PROVIDER') {
            $query->where('provider_id', (int) $user['id']);
        } elseif ($providerId) {
            $query->where('provider_id', (int) $providerId);
        }
        if ($dateFrom) {
            $query->where('date_time', '>=', AppointmentService::parseDateTime($dateFrom . ' 12:00 AM'));
        }
        if ($dateTo) {
            $query->where('date_time', '<=', AppointmentService::parseDateTime($dateTo . ' 11:59 PM'));
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        $required = ['customerId', 'dateTime', 'location', 'providerId'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_error("Field '{$field}' is required", 400);
            }
        }

        try {
            $appointment = AppointmentService::create($data, $user['id']);
            return json_success($appointment->toArray(), 'Appointment created', 201);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function read($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }
        return json_success($appointment->toArray());
    }

    public function history($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }

        $history = $appointment->history()->order('created_at', 'asc')->select()->toArray();
        return json_success($history);
    }

    public function confirm($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }
        $user = session('user');
        try {
            $appointment = AppointmentService::confirm((int) $id, $user['id']);
            return json_success($appointment->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            \think\facade\Log::error('appointment.confirm failed', ['id' => $id, 'error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Could not confirm appointment'), 400);
        }
    }

    public function reschedule($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }

        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['newDateTime'])) {
            return json_error('newDateTime is required', 400);
        }

        $isAdmin = $user['role'] === 'SYSTEM_ADMIN';
        $reason  = $data['reason'] ?? null;

        try {
            $appointment = AppointmentService::reschedule(
                (int) $id,
                $data['newDateTime'],
                $user['id'],
                $isAdmin,
                $reason
            );
            return json_success($appointment->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function cancel($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }

        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        try {
            $appointment = AppointmentService::cancel((int) $id, $user['id'], $data['reason'] ?? null);
            return json_success($appointment->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            \think\facade\Log::error('appointment.cancel failed', ['id' => $id, 'error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Could not cancel appointment'), 400);
        }
    }

    public function repair($id)
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if ($user['role'] !== 'SYSTEM_ADMIN') {
            return json_error('Repair is admin-only', 403);
        }
        if (empty($data['targetState']) || empty($data['reason'])) {
            return json_error('targetState and reason are required', 400);
        }

        try {
            $appointment = AppointmentService::repair((int) $id, $data['targetState'], $user['id'], $data['reason']);
            return json_success($appointment->toArray());
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function checkIn($id)
    {
        $user = session('user');
        try {
            $appointment = AppointmentService::checkIn((int) $id, $user['id']);
            return json_success($appointment->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            \think\facade\Log::error('appointment action failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e), 400);
        }
    }

    public function checkOut($id)
    {
        $user = session('user');
        try {
            $appointment = AppointmentService::checkOut((int) $id, $user['id']);
            return json_success($appointment->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            \think\facade\Log::error('appointment action failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e), 400);
        }
    }

    public function uploadAttachment($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }

        $user = session('user');
        $file = $this->request->file('file');

        if (!$file) {
            return json_error('No file uploaded', 400);
        }

        try {
            $attachment = AppointmentService::uploadAttachment((int) $id, $file, $user['id']);
            return json_success($attachment->toArray(), 'File uploaded', 201);
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function listAttachments($id)
    {
        $appointment = AppointmentModel::find($id);
        if (!$appointment) {
            return json_error('Appointment not found', 404);
        }
        if ($deny = $this->assertCanAccess($appointment)) {
            return $deny;
        }

        $attachments = $appointment->attachments()->select()->toArray();
        return json_success($attachments);
    }
}
