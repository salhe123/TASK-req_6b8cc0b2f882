<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateReviewVersionsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_review_versions')
            ->addColumn('assignment_id', 'integer', ['signed' => false])
            ->addColumn('scorecard_id', 'integer', ['signed' => false])
            ->addColumn('ratings', 'json')
            ->addColumn('total_score', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
            ->addColumn('status', 'enum', [
                'values'  => ['DRAFT', 'SUBMITTED', 'PUBLISHED'],
                'default' => 'DRAFT',
            ])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['assignment_id'])
            ->addIndex(['scorecard_id'])
            ->addIndex(['status'])
            ->addForeignKey('assignment_id', 'pp_review_assignments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('scorecard_id', 'pp_scorecards', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
