<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateThrottleConfigTable extends Migrator
{
    public function change()
    {
        $this->table('pp_throttle_config')
            ->addColumn('key', 'string', ['limit' => 50])
            ->addColumn('value', 'integer')
            ->addColumn('description', 'string', ['limit' => 255])
            ->addColumn('updated_by', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['key'], ['unique' => true])
            ->create();
    }
}
