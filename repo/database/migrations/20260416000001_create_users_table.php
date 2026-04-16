<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateUsersTable extends Migrator
{
    public function change()
    {
        $this->table('pp_users')
            ->addColumn('username', 'string', ['limit' => 50])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('role', 'enum', [
                'values' => [
                    'PRODUCTION_PLANNER',
                    'OPERATOR',
                    'SERVICE_COORDINATOR',
                    'PROVIDER',
                    'REVIEWER',
                    'REVIEW_MANAGER',
                    'PRODUCT_SPECIALIST',
                    'CONTENT_MODERATOR',
                    'FINANCE_CLERK',
                    'SYSTEM_ADMIN',
                ],
            ])
            ->addColumn('status', 'enum', [
                'values'  => ['ACTIVE', 'LOCKED', 'INACTIVE'],
                'default' => 'ACTIVE',
            ])
            ->addColumn('failed_login_attempts', 'integer', ['default' => 0])
            ->addColumn('locked_at', 'datetime', ['null' => true])
            ->addColumn('last_login_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['role'])
            ->addIndex(['status'])
            ->create();
    }
}
