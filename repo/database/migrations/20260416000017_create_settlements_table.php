<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateSettlementsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_settlements')
            ->addColumn('week_ending', 'date')
            ->addColumn('platform_fee_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 8.00])
            ->addColumn('total_settled', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0.00])
            ->addColumn('platform_fee', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0.00])
            ->addColumn('provider_payouts', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => 0.00])
            ->addColumn('transaction_count', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'enum', [
                'values'  => ['PENDING', 'COMPLETED', 'FAILED'],
                'default' => 'PENDING',
            ])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['week_ending'])
            ->addIndex(['status'])
            ->addForeignKey('created_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
