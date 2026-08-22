<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.27 (audit 14 couches — layer 9, fake/dead) : `strategy_kros`
 * (modèle `KRO`, factory `KROFactory`) était un doublon mort du vrai concept
 * de résultat-clé — `StrategyKeyResult`/`strategy_key_results`, réellement
 * utilisé par `OkrService`/`OkrController` sur toute la chaîne OKR. Confirmé
 * par grep exhaustif : `KRO`/`strategy_kros` n'avait strictement AUCUN
 * appelant nulle part dans le module (ni contrôleur, ni route, ni test) —
 * pas même un test isolé, contrairement au motif habituel « orphelin mais
 * testé ». Le modèle était en plus déjà cassé indépendamment de son
 * inutilisation : sa relation `kpi(): BelongsTo` référence `KPI::class`, une
 * classe qui n'existe nulle part dans ce module (le vrai modèle est
 * `StrategyKpi`) — un « class not found » garanti si jamais elle avait été
 * touchée. Classé « mort confirmé, à supprimer » (pas « à activer » — le
 * vrai concept qu'il visait à couvrir est déjà pleinement construit ailleurs
 * dans ce même module).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('strategy_kros');
    }

    public function down(): void
    {
        Schema::create('strategy_kros', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('objective_id');
            $table->unsignedBigInteger('kpi_id');
            $table->decimal('target', 15, 4)->nullable();
            $table->decimal('baseline', 15, 4)->nullable();
            $table->decimal('current', 15, 4)->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->timestamps();
        });
    }
};
