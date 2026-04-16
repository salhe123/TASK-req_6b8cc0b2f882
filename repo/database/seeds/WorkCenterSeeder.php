<?php

use think\migration\Seeder;

class WorkCenterSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $workCenters = [
            [
                'name'           => 'Assembly Line A',
                'capacity_hours' => 40.00,
                'status'         => 'ACTIVE',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'Assembly Line B',
                'capacity_hours' => 40.00,
                'status'         => 'ACTIVE',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'Testing Lab',
                'capacity_hours' => 35.00,
                'status'         => 'ACTIVE',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'Packaging Station',
                'capacity_hours' => 30.00,
                'status'         => 'ACTIVE',
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];

        $table = $this->table('pp_work_centers');
        $table->insert($workCenters)->saveData();
    }
}
