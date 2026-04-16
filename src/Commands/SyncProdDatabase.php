<?php

namespace Nodir\DbSync\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SyncProdDatabase extends Command
{
    protected $signature = 'db:sync-prod 
                            {--keep-dump : Dump faylini o\'chirmaslik}
                            {--data-only : Faqat ma\'lumotlarni olish (schemasiz)}
                            {--fresh : Migrate:fresh keyin data yuklash}';

    protected $description = 'Prod PostgreSQL bazasini localga ko\'chirish';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Bu buyruqni productionda ishlatib bo\'lmaydi!');
            return self::FAILURE;
        }

        $ssh = config('db-sync.ssh');
        $prodDb = config('db-sync.database');
        $local = config('database.connections.' . config('database.default'));

        if (empty($ssh['host']) || empty($prodDb['name']) || empty($prodDb['password'])) {
            $this->error('.env da PROD_SSH_HOST, PROD_DB_NAME va PROD_DB_PASSWORD ni to\'ldiring');
            $this->line('Namuna uchun: vendor/nodir/db-sync/README.md ga qarang');
            return self::FAILURE;
        }

        $this->warn("Local bazangiz '{$local['database']}' to'liq qayta yoziladi.");
        $this->line("Prod server: {$ssh['host']} → {$prodDb['name']}");
        if (!$this->confirm('Davom etamizmi?', false)) {
            return self::SUCCESS;
        }

        $dumpPath = config('db-sync.dump_path');
        if (!is_dir($dumpPath)) {
            mkdir($dumpPath, 0755, true);
        }

        $dumpFile = $dumpPath . '/prod_' . date('Ymd_His') . '.dump';

        $this->info('📦 Proddan dump olinmoqda...');
        if (!$this->dumpFromProd($ssh, $prodDb, $dumpFile)) {
            return self::FAILURE;
        }

        $this->info('✅ Dump olindi: ' . $this->formatBytes(filesize($dumpFile)));

        if ($this->option('fresh')) {
            $this->info('🔄 Local bazani tozalash (migrate:fresh)...');
            $this->call('migrate:fresh');
        }

        $this->info('📥 Localga tiklanmoqda...');
        if (!$this->restoreToLocal($local, $dumpFile)) {
            return self::FAILURE;
        }

        $this->info('🔧 Sequence\'lar to\'g\'rilanmoqda...');
        $this->fixSequences();

        if (!$this->option('keep-dump')) {
            @unlink($dumpFile);
            $this->info('🗑  Dump fayl o\'chirildi');
        } else {
            $this->info("💾 Dump saqlandi: {$dumpFile}");
        }

        $this->info('🎉 Sync muvaffaqiyatli yakunlandi!');
        return self::SUCCESS;
    }

    protected function dumpFromProd(array $ssh, array $db, string $dumpFile): bool
    {
        $dataOnly = $this->option('data-only') ? '--data-only' : '';

        $remoteCmd = sprintf(
            'PGPASSWORD=%s pg_dump -U %s -h %s -p %d -d %s -F c %s',
            escapeshellarg($db['password']),
            escapeshellarg($db['user']),
            escapeshellarg($db['host']),
            $db['port'],
            escapeshellarg($db['name']),
            $dataOnly
        );

        $sshCmd = sprintf(
            'ssh -p %d -o BatchMode=yes %s@%s %s > %s',
            $ssh['port'],
            escapeshellarg($ssh['user']),
            escapeshellarg($ssh['host']),
            escapeshellarg($remoteCmd),
            escapeshellarg($dumpFile)
        );

        $process = Process::fromShellCommandline($sshCmd);
        $process->setTimeout(config('db-sync.timeout', 3600));
        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR && !str_contains($buffer, 'Warning')) {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful() || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
            $this->error('Dump olishda xatolik: ' . $process->getErrorOutput());
            return false;
        }

        return true;
    }

    protected function restoreToLocal(array $local, string $dumpFile): bool
    {
        $cmd = sprintf(
            'PGPASSWORD=%s pg_restore -h %s -p %s -U %s -d %s --no-owner --no-privileges --clean --if-exists %s',
            escapeshellarg($local['password']),
            escapeshellarg($local['host']),
            escapeshellarg($local['port']),
            escapeshellarg($local['username']),
            escapeshellarg($local['database']),
            escapeshellarg($dumpFile)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(config('db-sync.timeout', 3600));
        $process->run();

        if (!$process->isSuccessful()) {
            $errors = $process->getErrorOutput();
            if (str_contains($errors, 'FATAL') || str_contains($errors, 'could not')) {
                $this->error('Tiklashda xatolik: ' . $errors);
                return false;
            }
            $this->warn('Ba\'zi ogohlantirishlar bor (odatda xavfsiz)');
        }

        return true;
    }

    protected function fixSequences(): void
    {
        $tables = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public' 
            AND column_default LIKE 'nextval%'
        ");

        foreach ($tables as $t) {
            try {
                DB::statement("
                    SELECT setval(
                        pg_get_serial_sequence(?, ?),
                        COALESCE((SELECT MAX({$t->column_name}) FROM \"{$t->table_name}\"), 1),
                        true
                    )
                ", [$t->table_name, $t->column_name]);
            } catch (\Exception $e) {
                // ignore
            }
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}