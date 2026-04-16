<?php

use think\migration\Seeder;

class RoleAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // Default admin user (password: Admin12345!)
        $users = [
            [
                'username'              => 'admin',
                'password'              => password_hash('Admin12345!', PASSWORD_BCRYPT),
                'role'                  => 'SYSTEM_ADMIN',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'planner1',
                'password'              => password_hash('Planner12345!', PASSWORD_BCRYPT),
                'role'                  => 'PRODUCTION_PLANNER',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'coordinator1',
                'password'              => password_hash('Coordinator1!', PASSWORD_BCRYPT),
                'role'                  => 'SERVICE_COORDINATOR',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'provider1',
                'password'              => password_hash('Provider1234!', PASSWORD_BCRYPT),
                'role'                  => 'PROVIDER',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'reviewer1',
                'password'              => password_hash('Reviewer1234!', PASSWORD_BCRYPT),
                'role'                  => 'REVIEWER',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'reviewmanager1',
                'password'              => password_hash('ReviewMgr1234!', PASSWORD_BCRYPT),
                'role'                  => 'REVIEW_MANAGER',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'specialist1',
                'password'              => password_hash('Specialist123!', PASSWORD_BCRYPT),
                'role'                  => 'PRODUCT_SPECIALIST',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'operator1',
                'password'              => password_hash('Operator1234!', PASSWORD_BCRYPT),
                'role'                  => 'OPERATOR',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'moderator1',
                'password'              => password_hash('Moderator123!', PASSWORD_BCRYPT),
                'role'                  => 'CONTENT_MODERATOR',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'username'              => 'finance1',
                'password'              => password_hash('Finance12345!', PASSWORD_BCRYPT),
                'role'                  => 'FINANCE_CLERK',
                'status'                => 'ACTIVE',
                'failed_login_attempts' => 0,
                'locked_at'             => null,
                'last_login_at'         => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
        ];

        $table = $this->table('pp_users');
        $table->insert($users)->saveData();
    }
}
