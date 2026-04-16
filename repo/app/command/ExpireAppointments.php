<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\AppointmentService;

class ExpireAppointments extends Command
{
    protected function configure(): void
    {
        $this->setName('appointment:expire')
             ->setDescription('Auto-expire PENDING appointments older than 24 hours');
    }

    protected function execute(Input $input, Output $output): int
    {
        $count = AppointmentService::expirePending();
        $output->writeln("Expired {$count} pending appointment(s).");
        return 0;
    }
}
