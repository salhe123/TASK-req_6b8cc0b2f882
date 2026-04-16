<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppointmentHistoryTable extends Migrator
{
    public function change()
    {
        $this->table('pp_appointment_history')
            ->addColumn('appointment_id', 'integer', ['signed' => false])
            ->addColumn('from_status', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('to_status', 'string', ['limit' => 20])
            ->addColumn('changed_by', 'integer', ['signed' => false])
            ->addColumn('reason', 'text', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['appointment_id'])
            ->addIndex(['changed_by'])
            ->addIndex(['created_at'])
            ->addForeignKey('appointment_id', 'pp_appointments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('changed_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
