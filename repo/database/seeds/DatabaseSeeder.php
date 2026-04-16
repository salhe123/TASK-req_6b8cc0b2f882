<?php

use think\migration\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('RoleAndAdminSeeder');
        $this->call('WorkCenterSeeder');
        $this->call('ThrottleConfigSeeder');
    }
}
