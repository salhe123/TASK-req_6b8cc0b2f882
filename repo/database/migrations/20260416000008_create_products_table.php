<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateProductsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_products')
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('category', 'enum', [
                'values' => ['CPU', 'GPU', 'MOTHERBOARD'],
            ])
            ->addColumn('vendor_name', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('specs', 'json')
            ->addColumn('normalized_specs', 'json', ['null' => true])
            ->addColumn('fingerprint', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('completeness_score', 'decimal', ['precision' => 5, 'scale' => 4, 'null' => true])
            ->addColumn('consistency_score', 'decimal', ['precision' => 5, 'scale' => 4, 'null' => true])
            ->addColumn('status', 'enum', [
                'values'  => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'],
                'default' => 'DRAFT',
            ])
            ->addColumn('submitted_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['category'])
            ->addIndex(['status'])
            ->addIndex(['fingerprint'])
            ->addIndex(['vendor_name'])
            ->addIndex(['submitted_by'])
            ->addIndex(['created_by'])
            ->addForeignKey('submitted_by', 'pp_users', 'id', ['delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
