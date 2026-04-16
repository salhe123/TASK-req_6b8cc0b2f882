<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class WorkCenter extends Model
{
    protected $table = 'pp_work_centers';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
    ];
}
