<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateLedgerEntriesTable extends Migrator
{
    public function change()
    {
        $this->table('pp_ledger_entries')
            ->addColumn('settlement_id', 'integer', ['signed' => false])
            ->addColumn('provider_id', 'integer', ['signed' => false])
            ->addColumn('appointment_id', 'integer', ['signed' => false])
            ->addColumn('gross_amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('platform_fee', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('net_amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['settlement_id'])
            ->addIndex(['provider_id'])
            ->addIndex(['appointment_id'])
            ->addForeignKey('settlement_id', 'pp_settlements', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('provider_id', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('appointment_id', 'pp_appointments', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
