<?php

declare(strict_types=1);

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Services\TreasuryImportService;

/**
 * Chantier 32 (volet A2) — un wrapper non-interactif du pipeline
 * preview→commit déjà réel et déjà testé de TreasuryImportService
 * (Chantier 15), pour importer en une seule commande l'historique de
 * caisse/banque d'un opérateur au moment du déploiement, plutôt qu'un
 * aller-retour manuel écran par écran.
 *
 * N'écrit jamais rien tant que --commit n'est pas passé : sans ce flag,
 * la commande se contente d'afficher un aperçu (comptage par modèle
 * suggéré), le même comportement que l'écran Accounting/TreasuryImport.
 *
 * Aucune donnée réelle de l'utilisateur n'est jamais committée dans ce
 * dépôt — cette commande lit un fichier fourni par l'opérateur au moment
 * de l'exécution, jamais un chemin codé en dur.
 */
class ImportTreasuryHistoryCommand extends Command
{
    protected $signature = 'accounting:import-treasury-history
        {file : Chemin vers le fichier CSV/XLSX à importer}
        {--treasury-account=530 : Code du compte de trésorerie (530 Caisse, 512 Banque, 531 Mvola, 532 Airtel Money)}
        {--bank-account-id= : ID du compte bancaire pour le pré-rapprochement (comptes 512/531/532 uniquement)}
        {--commit : Écrit réellement les écritures — sans ce flag, aperçu seul}';

    protected $description = "Importe un historique de caisse/banque (CSV/XLSX) via le pipeline TreasuryImportService — aperçu par défaut, --commit pour écrire.";

    public function handle(TreasuryImportService $service): int
    {
        $path = $this->argument('file');
        $treasuryAccount = (string) $this->option('treasury-account');
        $bankAccountId = $this->option('bank-account-id') !== null ? (int) $this->option('bank-account-id') : null;

        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$path}");

            return self::FAILURE;
        }

        if (! $service->isSupportedTreasuryAccount($treasuryAccount)) {
            $this->error("Compte de trésorerie non supporté : {$treasuryAccount} (attendus : 530, 512, 531, 532)");

            return self::FAILURE;
        }

        $parsed = $service->parseFile($path);
        if ($parsed['rows'] === []) {
            $this->warn('Aucune ligne exploitable trouvée dans le fichier (colonnes date/libellé/montant introuvables ou fichier vide).');

            return self::SUCCESS;
        }

        $suggested = $service->suggest($parsed['rows']);

        $byTemplate = [];
        foreach ($suggested as $row) {
            $byTemplate[$row['suggested_template_code']] = ($byTemplate[$row['suggested_template_code']] ?? 0) + 1;
        }

        $this->info(count($suggested) . ' ligne(s) analysée(s), répartition par modèle suggéré :');
        $this->table(['Modèle', 'Nombre de lignes'], collect($byTemplate)->map(fn ($count, $code) => [$code, $count])->toArray());

        if (! $this->option('commit')) {
            $this->comment('Aperçu seul — relancez avec --commit pour écrire réellement ces écritures.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (array $row) => [...$row, 'template_code' => $row['suggested_template_code']], $suggested);

        $result = $service->commit($rows, $treasuryAccount, $bankAccountId, null);

        $this->info(($result['count'] ?? 0) . " écriture(s) créée(s) dans le journal (débit {$result['total_debit']} / crédit {$result['total_credit']}).");

        return self::SUCCESS;
    }
}
