<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateReviewAssignmentsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_review_assignments')
            ->addColumn('product_id', 'integer', ['signed' => false])
            ->addColumn('reviewer_id', 'integer', ['signed' => false])
            ->addColumn('blind', 'boolean', ['default' => false])
            ->addColumn('status', 'enum', [
                'values'  => ['ASSIGNED', 'SUBMITTED', 'PUBLISHED'],
                'default' => 'ASSIGNED',
            ])
            ->addColumn('assigned_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['product_id'])
            ->addIndex(['reviewer_id'])
            ->addIndex(['status'])
            ->addForeignKey('product_id', 'pp_products', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('reviewer_id', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
