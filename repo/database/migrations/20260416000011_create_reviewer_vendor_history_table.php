<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateReviewerVendorHistoryTable extends Migrator
{
    public function change()
    {
        $this->table('pp_reviewer_vendor_history')
            ->addColumn('reviewer_id', 'integer', ['signed' => false])
            ->addColumn('vendor_name', 'string', ['limit' => 255])
            ->addColumn('association_start', 'date')
            ->addColumn('association_end', 'date', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['reviewer_id'])
            ->addIndex(['vendor_name'])
            ->addIndex(['association_end'])
            ->addForeignKey('reviewer_id', 'pp_users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
