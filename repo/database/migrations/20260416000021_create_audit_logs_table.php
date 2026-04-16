<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateAuditLogsTable extends Migrator
{
    public function change()
    {
        $this->table('pp_audit_logs')
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 100])
            ->addColumn('entity_type', 'string', ['limit' => 50])
            ->addColumn('entity_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('before_data', 'json', ['null' => true])
            ->addColumn('after_data', 'json', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['user_id'])
            ->addIndex(['action'])
            ->addIndex(['entity_type', 'entity_id'])
            ->addIndex(['created_at'])
            ->create();
    }

    /**
     * After creating the table, add a MySQL trigger to prevent
     * UPDATE and DELETE operations (append-only enforcement).
     */
    public function up()
    {
        $this->change();

        $this->execute("
            CREATE TRIGGER pp_audit_logs_no_update
            BEFORE UPDATE ON pp_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'UPDATE not allowed on audit_logs: append-only table';
            END
        ");

        $this->execute("
            CREATE TRIGGER pp_audit_logs_no_delete
            BEFORE DELETE ON pp_audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'DELETE not allowed on audit_logs: append-only table';
            END
        ");
    }

    public function down()
    {
        $this->execute('DROP TRIGGER IF EXISTS pp_audit_logs_no_update');
        $this->execute('DROP TRIGGER IF EXISTS pp_audit_logs_no_delete');
        $this->drop('pp_audit_logs');
    }
}
