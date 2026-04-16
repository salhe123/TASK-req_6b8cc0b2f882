<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\RiskService;

class RiskScore extends Command
{
    protected function configure(): void
    {
        $this->setName('risk:score')
             ->setDescription('Calculate nightly risk scores and detect anomalies');
    }

    protected function execute(Input $input, Output $output): int
    {
        $scores = RiskService::calculateScores();
        $output->writeln("Calculated risk scores for {$scores} user(s).");

        $flags = RiskService::detectAnomalies();
        $output->writeln("Detected {$flags} anomaly flag(s).");

        return 0;
    }
}
