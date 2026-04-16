<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateScorecardsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_scorecards')
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('status', 'enum', [
                'values'  => ['ACTIVE', 'INACTIVE'],
                'default' => 'ACTIVE',
            ])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['status'])
            ->addForeignKey('created_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();

        $this->table('pp_scorecard_dimensions')
            ->addColumn('scorecard_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('weight', 'integer', ['signed' => false])
            ->addColumn('sort_order', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['scorecard_id'])
            ->addForeignKey('scorecard_id', 'pp_scorecards', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
