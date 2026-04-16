<?php

use think\migration\Seeder;

class ThrottleConfigSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $config = [
            [
                'key'         => 'requests_per_minute',
                'value'       => 60,
                'description' => 'Maximum API requests per minute per account',
                'updated_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'appointments_per_hour',
                'value'       => 10,
                'description' => 'Maximum appointment creations per hour per account',
                'updated_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'postings_per_day',
                'value'       => 20,
                'description' => 'Threshold for excessive posting anomaly flag',
                'updated_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'cancellations_per_week',
                'value'       => 5,
                'description' => 'Threshold for excessive cancellation anomaly flag',
                'updated_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        $table = $this->table('pp_throttle_config');
        $table->insert($config)->saveData();
    }
}
