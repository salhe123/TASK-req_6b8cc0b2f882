<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AppointmentAttachment extends Model
{
    protected $table = 'pp_appointment_attachments';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id'             => 'integer',
        'appointment_id' => 'integer',
        'file_size'      => 'integer',
        'uploaded_by'    => 'integer',
    ];
}
