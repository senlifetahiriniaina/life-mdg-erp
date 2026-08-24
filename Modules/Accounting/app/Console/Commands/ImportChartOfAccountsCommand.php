<?php

declare(strict_types=1);

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Models\ChartOfAccount;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Chantier 32 (volet A2) — le plan comptable de base est déjà seedé et
 * déjà adapté Madagascar/textile (Chantiers 12/13/17, voir
 * AccountingDatabaseSeeder) ; cette commande permet d'étendre/personnaliser
 * ce socle avec les comptes spécifiques d'un opérateur, à partir de son
 * propre fichier Excel/CSV (code/libellé/type/classe), sans jamais toucher
 * au code — utilisable au déploiement initial ou à tout moment ensuite.
 *
 * `updateOrCreate` par code : un compte déjà présent (du seed de base ou
 * d'un import précédent) est mis à jour, jamais dupliqué.
 */
class ImportChartOfAccountsCommand extends Command
{
    protected $signature = 'accounting:import-chart-of-accounts
        {file : Chemin vers le fichier CSV/XLSX (colonnes : code, libellé/nom, type, description)}
        {--dry-run : Aperçu seul, n\'écrit rien}';

    protected $description = 'Étend/personnalise le plan comptable OHADA de base avec les comptes fournis dans un fichier.';

    private const TYPE_ALIASES = [
        'actif' => 'asset', 'asset' => 'asset',
        'passif' => 'liability', 'liability' => 'liability',
        'capitaux propres' => 'equity', 'equity' => 'equity',
        'produit' => 'revenue', 'revenu' => 'revenue', 'revenue' => 'revenue',
        'charge' => 'expense', 'expense' => 'expense',
    ];

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        $rows = $this->parseFile($path);

        if ($rows === []) {
            $this->warn('Aucune ligne exploitable trouvée (colonnes code/libellé introuvables ou fichier vide).');

            return self::SUCCESS;
        }

        $this->info(count($rows) . ' compte(s) trouvé(s) dans le fichier.');
        $this->table(['Code', 'Libellé', 'Type'], array_map(fn ($r) => [$r['code'], $r['name'], $r['type'] ?? '—'], $rows));

        if ($this->option('dry-run')) {
            $this->comment('Aperçu seul (--dry-run) — rien n\'a été écrit.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                ChartOfAccount::updateOrCreate(
                    ['code' => $row['code']],
                    array_filter([
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'description' => $row['description'],
                    ], fn ($v) => $v !== null)
                );
            }
        });

        $this->info(count($rows) . ' compte(s) importé(s)/mis à jour.');

        return self::SUCCESS;
    }

    /** @return list<array{code: string, name: string, type: ?string, description: ?string}> */
    private function parseFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $records = $ext === 'csv' || $ext === 'txt'
            ? $this->parseCsv($path)
            : $this->parseSpreadsheet($path);

        if ($records === []) {
            return [];
        }

        $headers = array_map(fn ($h) => Str::of((string) $h)->lower()->ascii()->trim()->value(), array_shift($records));
        $codeKey = $this->findColumn($headers, ['code', 'compte', 'numero']);
        $nameKey = $this->findColumn($headers, ['libelle', 'nom', 'name', 'intitule']);
        $typeKey = $this->findColumn($headers, ['type', 'classe', 'nature']);
        $descriptionKey = $this->findColumn($headers, ['description', 'notes']);

        if ($codeKey === null || $nameKey === null) {
            return [];
        }

        $rows = [];
        foreach ($records as $record) {
            $record = array_pad($record, count($headers), null);
            $byHeader = array_combine($headers, array_slice($record, 0, count($headers)));

            $code = trim((string) ($byHeader[$codeKey] ?? ''));
            $name = trim((string) ($byHeader[$nameKey] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $rawType = $typeKey !== null ? Str::lower(trim((string) ($byHeader[$typeKey] ?? ''))) : null;

            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => $rawType !== null ? (self::TYPE_ALIASES[$rawType] ?? null) : null,
                'description' => $descriptionKey !== null ? (trim((string) ($byHeader[$descriptionKey] ?? '')) ?: null) : null,
            ];
        }

        return $rows;
    }

    private function parseCsv(string $path): array
    {
        $records = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $records[] = count($row) === 1 ? explode(',', $row[0]) : $row;
        }
        fclose($handle);

        return $records;
    }

    private function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    private function findColumn(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            foreach ($headers as $header) {
                if (str_contains($header, $candidate)) {
                    return $header;
                }
            }
        }

        return null;
    }
}
