<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateModerationDecisionsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_moderation_decisions')
            ->addColumn('item_type', 'enum', [
                'values' => ['PRODUCT', 'MERGE'],
            ])
            ->addColumn('item_id', 'integer', ['signed' => false])
            ->addColumn('action', 'enum', [
                'values' => ['APPROVE', 'REJECT', 'MERGE', 'DISTINCT'],
            ])
            ->addColumn('moderator_id', 'integer', ['signed' => false])
            ->addColumn('before_snapshot', 'json', ['null' => true])
            ->addColumn('after_snapshot', 'json', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['item_type', 'item_id'])
            ->addIndex(['moderator_id'])
            ->addIndex(['action'])
            ->addIndex(['created_at'])
            ->addForeignKey('moderator_id', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
