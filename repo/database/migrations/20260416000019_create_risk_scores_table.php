<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateRiskScoresTable extends Migrator
{
    public function change()
    {
        $this->table('pp_risk_scores')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('score', 'decimal', ['precision' => 5, 'scale' => 2])
            ->addColumn('success_rate', 'decimal', ['precision' => 5, 'scale' => 4])
            ->addColumn('dispute_rate', 'decimal', ['precision' => 5, 'scale' => 4])
            ->addColumn('cancellation_rate', 'decimal', ['precision' => 5, 'scale' => 4])
            ->addColumn('calculated_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addIndex(['user_id'])
            ->addIndex(['score'])
            ->addIndex(['calculated_at'])
            ->addForeignKey('user_id', 'pp_users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
