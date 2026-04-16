<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateDeviceFingerprintsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_device_fingerprints')
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('fingerprint_hash', 'string', ['limit' => 64])
            ->addColumn('user_agent', 'string', ['limit' => 500])
            ->addColumn('screen_resolution', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('timezone', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('fonts_hash', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45])
            ->addColumn('first_seen_at', 'datetime')
            ->addColumn('last_seen_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['user_id'])
            ->addIndex(['fingerprint_hash'])
            ->addIndex(['ip_address'])
            ->addForeignKey('user_id', 'pp_users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
