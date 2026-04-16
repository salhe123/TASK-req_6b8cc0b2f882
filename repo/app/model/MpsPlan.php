<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class MpsPlan extends Model
{
    protected $table = 'pp_mps_plans';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id'             => 'integer',
        'product_id'     => 'integer',
        'work_center_id' => 'integer',
        'quantity'        => 'integer',
        'created_by'     => 'integer',
    ];

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'mps_id');
    }
}
