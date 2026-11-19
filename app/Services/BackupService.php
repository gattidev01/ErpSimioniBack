<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Backup do banco sem depender do mysqldump instalado:
 * gera um .sql.gz com CREATE TABLE + INSERTs (MySQL) ou copia o arquivo (SQLite).
 */
class BackupService
{
    public const PASTA = 'backups';

    /** Tabelas que não precisam ir para o backup */
    private const IGNORAR = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function gerar(): string
    {
        $disco = Storage::disk('local');
        $disco->makeDirectory(self::PASTA);
        $driver = DB::connection()->getDriverName();
        $nome = self::PASTA.'/backup-'.now()->format('Y-m-d-His');

        if ($driver === 'sqlite') {
            $origem = DB::connection()->getDatabaseName();
            if ($origem === ':memory:' || ! is_file($origem)) {
                throw new RuntimeException('Banco SQLite em memória não pode ser copiado.');
            }
            $destino = $nome.'.sqlite.gz';
            $disco->put($destino, gzencode(file_get_contents($origem), 6));
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $destino = $nome.'.sql.gz';
            $this->dumpMysql($disco->path($destino));
        } else {
            throw new RuntimeException("Backup não suportado para o banco {$driver}.");
        }

        $this->limparAntigos();

        return $destino;
    }

    /** Lista os backups existentes, do mais novo para o mais antigo */
    public function listar(): array
    {
        $disco = Storage::disk('local');

        return collect($disco->files(self::PASTA))
            ->filter(fn ($f) => preg_match('/backup-[\d-]+\.(sql|sqlite)\.gz$/', $f))
            ->map(fn ($f) => [
                'nome' => basename($f),
                'tamanho' => $disco->size($f),
                'criadoEm' => date(DATE_ATOM, $disco->lastModified($f)),
            ])
            ->sortByDesc('nome')
            ->values()
            ->all();
    }

    public function caminho(string $nome): ?string
    {
        // só aceita nomes gerados por este serviço (evita path traversal)
        if (! preg_match('/^backup-[\d-]+\.(sql|sqlite)\.gz$/', $nome)) {
            return null;
        }
        $rel = self::PASTA.'/'.$nome;

        return Storage::disk('local')->exists($rel) ? Storage::disk('local')->path($rel) : null;
    }

    private function limparAntigos(): void
    {
        $manter = (int) config('oficina.backup_manter', 14);
        $disco = Storage::disk('local');
        collect($this->listar())->slice($manter)->each(fn ($b) => $disco->delete(self::PASTA.'/'.$b['nome']));
    }

    private function dumpMysql(string $arquivo): void
    {
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($arquivo, 'wb6');
        if (! $gz) {
            throw new RuntimeException("Não foi possível criar {$arquivo}");
        }

        try {
            gzwrite($gz, "-- Backup ERP Oficina - ".now()->toDateTimeString()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tabelas = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'));

            foreach ($tabelas as $tabela) {
                if (in_array($tabela, self::IGNORAR, true)) {
                    continue;
                }
                $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$tabela}`"))[1];
                gzwrite($gz, "DROP TABLE IF EXISTS `{$tabela}`;\n{$create};\n\n");

                $lote = [];
                foreach (DB::table($tabela)->cursor() as $linha) {
                    $valores = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $linha);
                    $lote[] = '('.implode(',', $valores).')';
                    if (count($lote) >= 200) {
                        gzwrite($gz, "INSERT INTO `{$tabela}` VALUES ".implode(",\n", $lote).";\n");
                        $lote = [];
                    }
                }
                if ($lote) {
                    gzwrite($gz, "INSERT INTO `{$tabela}` VALUES ".implode(",\n", $lote).";\n");
                }
                gzwrite($gz, "\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }
    }
}
