<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreatePaymentsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_payments')
            ->addColumn('import_batch_id', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2])
            ->addColumn('payer_name', 'string', ['limit' => 255])
            ->addColumn('reference', 'string', ['limit' => 255])
            ->addColumn('payment_date', 'date')
            ->addColumn('status', 'enum', [
                'values'  => ['PENDING', 'RECONCILED', 'DISPUTED'],
                'default' => 'PENDING',
            ])
            ->addColumn('checksum', 'string', ['limit' => 64])
            ->addColumn('source_row', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['import_batch_id'])
            ->addIndex(['status'])
            ->addIndex(['payment_date'])
            ->addIndex(['reference'])
            ->addIndex(['checksum'])
            ->create();
    }
}
