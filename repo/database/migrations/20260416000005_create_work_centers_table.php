<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateWorkCentersTable extends Migrator
{
    public function change()
    {
        $this->table('pp_work_centers')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('capacity_hours', 'decimal', ['precision' => 8, 'scale' => 2])
            ->addColumn('status', 'enum', [
                'values'  => ['ACTIVE', 'INACTIVE'],
                'default' => 'ACTIVE',
            ])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }
}
