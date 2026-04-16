<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAnomalyFlagsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_anomaly_flags')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('flag_type', 'string', ['limit' => 50])
            ->addColumn('details', 'json', ['null' => true])
            ->addColumn('status', 'enum', [
                'values'  => ['OPEN', 'CLEARED'],
                'default' => 'OPEN',
            ])
            ->addColumn('cleared_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('cleared_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['user_id'])
            ->addIndex(['flag_type'])
            ->addIndex(['status'])
            ->addIndex(['created_at'])
            ->addForeignKey('user_id', 'pp_users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('cleared_by', 'pp_users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
