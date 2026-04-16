<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AuditService;
use think\facade\Db;

class Risk extends BaseController
{
    public function scores()
    {
        $userId     = $this->request->get('userId', '');
        $scoreBelow = $this->request->get('scoreBelow', '');

        $query = Db::name('risk_scores')->order('calculated_at', 'desc');

        if ($userId) {
            $query->where('user_id', (int) $userId);
        }
        if ($scoreBelow !== '') {
            $query->where('score', '<', (float) $scoreBelow);
        }

        $list = $query->select()->toArray();
        return json_success($list);
    }

    public function flags()
    {
        $flags = Db::name('anomaly_flags')
            ->where('status', 'OPEN')
            ->order('created_at', 'desc')
            ->select()
            ->toArray();

        return json_success($flags);
    }

    public function ipScores()
    {
        $rows = \app\service\RiskService::scoreIpRisk();
        return json_success($rows);
    }

    public function clearFlag($id)
    {
        $flag = Db::name('anomaly_flags')->find($id);
        if (!$flag) {
            return json_error('Flag not found', 404);
        }

        $user = session('user');
        $now  = date('Y-m-d H:i:s');

        Db::name('anomaly_flags')->where('id', $id)->update([
            'status'     => 'CLEARED',
            'cleared_by' => $user['id'],
            'cleared_at' => $now,
        ]);

        AuditService::log('ANOMALY_CLEARED', 'anomaly_flag', (int) $id, $flag, [
            'status' => 'CLEARED',
        ]);

        return json_success(['id' => $id, 'status' => 'CLEARED']);
    }

    public function throttles()
    {
        $config = Db::name('throttle_config')->select()->toArray();
        return json_success($config);
    }

    public function updateThrottles()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');
        $now  = date('Y-m-d H:i:s');

        // Boundary validation — reject values that would effectively disable
        // the control or overwhelm the system. Ranges are inclusive.
        $bounds = [
            'requestsPerMinute'   => ['key' => 'requests_per_minute',   'min' => 1, 'max' => 10000],
            'appointmentsPerHour' => ['key' => 'appointments_per_hour', 'min' => 1, 'max' => 1000],
        ];

        $updated = [];
        foreach ($bounds as $field => $cfg) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (!is_numeric($data[$field])) {
                return json_error("{$field} must be a number", 400);
            }
            $v = (int) $data[$field];
            if ($v < $cfg['min'] || $v > $cfg['max']) {
                return json_error("{$field} must be between {$cfg['min']} and {$cfg['max']}", 400);
            }
            Db::name('throttle_config')
                ->where('key', $cfg['key'])
                ->update([
                    'value'      => $v,
                    'updated_by' => $user['id'],
                    'updated_at' => $now,
                ]);
            $updated[$field] = $v;
        }

        if (empty($updated)) {
            return json_error('No valid throttle fields supplied', 400);
        }

        AuditService::log('THROTTLE_CONFIG_UPDATED', 'throttle_config', null, null, $updated);

        return json_success($updated);
    }
}
