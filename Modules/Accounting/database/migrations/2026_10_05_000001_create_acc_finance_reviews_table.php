<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 26 (volet D) — trace d'une revue finance périodique (mensuelle
 * ou trimestrielle) : qui l'a faite, sur quelle période, avec quel budget
 * lié éventuel, et les commentaires de l'équipe finance. La réalisation
 * des objectifs commerciaux/du budget n'est jamais stockée ici — toujours
 * recalculée en direct depuis le grand livre réel/les objectifs validés
 * (voir FinanceReviewService), puisque acc_budget_lines.actual_amount
 * n'est alimenté par aucun chemin d'écriture réel dans cette application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_finance_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->unsignedBigInteger('budget_id')->nullable();
            $table->string('cadence', 20)->default('monthly'); // monthly|quarterly
            $table->date('period_start');
            $table->date('period_end');
            $table->date('review_date');
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_finance_reviews');
    }
};
