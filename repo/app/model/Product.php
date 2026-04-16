<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Product extends Model
{
    protected $table = 'pp_products';
    protected $autoWriteTimestamp = false;

    protected $json = ['specs', 'normalized_specs'];

    protected $type = [
        'id'           => 'integer',
        'submitted_by' => 'integer',
        'created_by'   => 'integer',
    ];

    public function scores()
    {
        return $this->hasMany(ProductScore::class, 'product_id');
    }
}
