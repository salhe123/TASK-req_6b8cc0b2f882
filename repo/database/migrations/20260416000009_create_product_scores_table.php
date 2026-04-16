<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateProductScoresTable extends Migrator
{
    public function change()
    {
        $this->table('pp_product_scores')
            ->addColumn('product_id', 'integer', ['signed' => false])
            ->addColumn('completeness_score', 'decimal', ['precision' => 5, 'scale' => 4])
            ->addColumn('consistency_score', 'decimal', ['precision' => 5, 'scale' => 4])
            ->addColumn('details', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['product_id'])
            ->addForeignKey('product_id', 'pp_products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
