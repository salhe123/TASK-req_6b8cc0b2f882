<?php
declare(strict_types=1);

namespace app\controller\provider;

use app\BaseController;
use app\model\Appointment;

class Queue extends BaseController
{
    public function index()
    {
        $user = session('user');
        $date = $this->request->get('date', date('m/d/Y'));

        // Parse date filter
        $parsed = \DateTime::createFromFormat('m/d/Y', $date);
        if (!$parsed) {
            $parsed = new \DateTime($date);
        }
        $dayStart = $parsed->format('Y-m-d') . ' 00:00:00';
        $dayEnd   = $parsed->format('Y-m-d') . ' 23:59:59';

        $appointments = Appointment::where('provider_id', $user['id'])
            ->where('date_time', '>=', $dayStart)
            ->where('date_time', '<=', $dayEnd)
            ->whereIn('status', ['PENDING', 'CONFIRMED', 'IN_PROGRESS'])
            ->order('date_time', 'asc')
            ->select()
            ->toArray();

        return json_success([
            'date'         => $parsed->format('m/d/Y'),
            'appointments' => $appointments,
            'count'        => count($appointments),
        ]);
    }
}
