<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMpsPlansTable extends Migrator
{
    public function change()
    {
        $this->table('pp_mps_plans')
            ->addColumn('product_id', 'integer', ['signed' => false])
            ->addColumn('work_center_id', 'integer', ['signed' => false])
            ->addColumn('week_start', 'date')
            ->addColumn('quantity', 'integer', ['signed' => false])
            ->addColumn('planned_hours', 'decimal', ['precision' => 8, 'scale' => 2])
            ->addColumn('status', 'enum', [
                'values'  => ['DRAFT', 'ACTIVE', 'COMPLETED'],
                'default' => 'DRAFT',
            ])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['product_id'])
            ->addIndex(['work_center_id'])
            ->addIndex(['week_start'])
            ->addIndex(['status'])
            ->addForeignKey('work_center_id', 'pp_work_centers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
