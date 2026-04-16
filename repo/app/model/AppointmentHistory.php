<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AppointmentHistory extends Model
{
    protected $table = 'pp_appointment_history';
    protected $autoWriteTimestamp = false;

    protected $json = ['metadata'];

    protected $type = [
        'id'             => 'integer',
        'appointment_id' => 'integer',
        'changed_by'     => 'integer',
    ];
}
