<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppointmentsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_appointments')
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('provider_id', 'integer', ['signed' => false])
            ->addColumn('date_time', 'datetime')
            ->addColumn('location', 'string', ['limit' => 16, 'default' => ''])
            ->addColumn('location_hint', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('location_encrypted', 'text')
            ->addColumn('status', 'enum', [
                'values'  => ['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'EXPIRED', 'CANCELLED'],
                'default' => 'PENDING',
            ])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['customer_id'])
            ->addIndex(['provider_id'])
            ->addIndex(['status'])
            ->addIndex(['date_time'])
            ->addIndex(['created_by'])
            ->addForeignKey('customer_id', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('provider_id', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->addForeignKey('created_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
