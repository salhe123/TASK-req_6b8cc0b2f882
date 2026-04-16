<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAppointmentAttachmentsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_appointment_attachments')
            ->addColumn('appointment_id', 'integer', ['signed' => false])
            ->addColumn('file_name', 'string', ['limit' => 255])
            ->addColumn('file_path', 'string', ['limit' => 500])
            ->addColumn('file_type', 'string', ['limit' => 50])
            ->addColumn('file_size', 'integer', ['signed' => false])
            ->addColumn('uploaded_by', 'integer', ['signed' => false])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['appointment_id'])
            ->addForeignKey('appointment_id', 'pp_appointments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('uploaded_by', 'pp_users', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
            ->create();
    }
}
