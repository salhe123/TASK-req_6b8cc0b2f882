<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateReceiptsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_receipts')
            ->addColumn('payment_id', 'integer', ['signed' => false])
            ->addColumn('appointment_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('receipt_number', 'string', ['limit' => 50])
            ->addColumn('signature', 'string', ['limit' => 64])
            ->addColumn('fingerprint', 'string', ['limit' => 64])
            ->addColumn('issued_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addIndex(['payment_id'])
            ->addIndex(['appointment_id'])
            ->addIndex(['receipt_number'], ['unique' => true])
            ->addIndex(['fingerprint'])
            ->addForeignKey('payment_id', 'pp_payments', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('appointment_id', 'pp_appointments', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
