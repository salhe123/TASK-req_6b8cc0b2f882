<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ProductScore extends Model
{
    protected $table = 'pp_product_scores';
    protected $autoWriteTimestamp = false;

    protected $json = ['details'];

    protected $type = [
        'id'         => 'integer',
        'product_id' => 'integer',
    ];
}
