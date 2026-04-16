<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * Non-destructive audit-log archive.
 *
 * The `pp_audit_logs` table is append-only and enforced at the database level via
 * BEFORE UPDATE/DELETE triggers. This command does NOT delete or drop triggers;
 * instead it copies rows older than the retention window into `pp_audit_logs_archive`
 * with an HMAC-signed integrity row appended to the archive table.
 *
 * Operators who want to reclaim space on the hot table must run an explicit,
 * separately-audited `audit:purge --confirmed` workflow (not provided here by design).
 */
class AuditArchive extends Command
{
    protected function configure(): void
    {
        $this->setName('audit:archive')
             ->setDescription('Copy audit logs older than 12 months into the archive table (non-destructive)');
    }

    protected function execute(Input $input, Output $output): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-12 months'));

        $this->ensureArchiveTable();

        $count = Db::name('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->where('id', 'NOT IN', function ($query) {
                $query->name('audit_logs_archive')->field('original_id');
            })
            ->count();

        if ($count === 0) {
            $output->writeln('No new audit logs to archive.');
            return 0;
        }

        $rows = Db::name('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->where('id', 'NOT IN', function ($query) {
                $query->name('audit_logs_archive')->field('original_id');
            })
            ->select()
            ->toArray();

        $now   = date('Y-m-d H:i:s');
        $batch = 'arc_' . date('Ymd_His');

        Db::startTrans();
        try {
            foreach ($rows as $r) {
                $canonical = $r['id'] . '|' . $r['action'] . '|' . $r['entity_type']
                    . '|' . ($r['entity_id'] ?? '') . '|' . $r['created_at'];
                $signature = hmac_sign($canonical);

                Db::name('audit_logs_archive')->insert([
                    'original_id'   => $r['id'],
                    'user_id'       => $r['user_id'],
                    'action'        => $r['action'],
                    'entity_type'   => $r['entity_type'],
                    'entity_id'     => $r['entity_id'],
                    'before_data'   => $r['before_data'],
                    'after_data'    => $r['after_data'],
                    'ip_address'    => $r['ip_address'],
                    'user_agent'    => $r['user_agent'],
                    'archive_batch' => $batch,
                    'signature'     => $signature,
                    'original_created_at' => $r['created_at'],
                    'archived_at'   => $now,
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $output->writeln('Archive failed: ' . $e->getMessage());
            return 1;
        }

        $output->writeln("Archived {$count} audit log(s) older than 12 months (batch {$batch}). Source rows preserved.");

        return 0;
    }

    private function ensureArchiveTable(): void
    {
        Db::execute("
            CREATE TABLE IF NOT EXISTS pp_audit_logs_archive (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                original_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT UNSIGNED NULL,
                before_data JSON NULL,
                after_data JSON NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(500) NULL,
                archive_batch VARCHAR(64) NOT NULL,
                signature VARCHAR(64) NOT NULL,
                original_created_at DATETIME NOT NULL,
                archived_at DATETIME NOT NULL,
                UNIQUE KEY uniq_original (original_id),
                INDEX idx_batch (archive_batch),
                INDEX idx_original_created (original_created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
