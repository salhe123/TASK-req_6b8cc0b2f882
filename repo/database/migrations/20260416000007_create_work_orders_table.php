<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateWorkOrdersTable extends Migrator
{
    public function change()
    {
        $this->table('pp_work_orders')
            ->addColumn('mps_id', 'integer', ['signed' => false])
            ->addColumn('work_center_id', 'integer', ['signed' => false])
            ->addColumn('product_id', 'integer', ['signed' => false])
            ->addColumn('quantity_planned', 'integer', ['signed' => false])
            ->addColumn('quantity_completed', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('quantity_rework', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('downtime_minutes', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('reason_code', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('status', 'enum', [
                'values'  => ['PENDING', 'IN_PROGRESS', 'COMPLETED'],
                'default' => 'PENDING',
            ])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['mps_id'])
            ->addIndex(['work_center_id'])
            ->addIndex(['product_id'])
            ->addIndex(['status'])
            ->addForeignKey('mps_id', 'pp_mps_plans', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('work_center_id', 'pp_work_centers', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
