<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Unit;

/**
 * Chantier 17 — catalogue de templates de produits pour le domaine
 * vestimentaire, couvrant les 4 familles citées par l'utilisateur : matières
 * premières, accessoires, vêtements semi-finis, produits finis. Chaque
 * template pointe vers une catégorie déjà seedée par DefaultDataSeeder
 * (qui porte elle-même le routage comptable suggéré — voir Category's
 * default_*_account_code) — ce seeder ne crée ni catégorie ni compte, il
 * suppose que DefaultDataSeeder + AccountingDatabaseSeeder ont déjà tourné.
 *
 * Idempotent via firstOrCreate sur `code`.
 */
class ProductTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $matieresPremieres = Category::where('name', 'Matières premières')->first();
        $accessoires = Category::where('name', 'Accessoires')->first();
        $semiFinis = Category::where('name', 'Vêtements semi-finis')->first();
        $produitsFinis = Category::where('name', 'Produits finis')->first();

        if (!$matieresPremieres || !$accessoires || !$semiFinis || !$produitsFinis) {
            // DefaultDataSeeder hasn't run (or ran against a schema this
            // seeder doesn't recognize) — nothing sane to attach templates
            // to, so skip rather than create orphan/mis-categorized templates.
            return;
        }

        $piece = Unit::where('name', 'Pièce')->first();
        $metre = Unit::where('name', 'Mètre')->first();
        $metreCarre = Unit::where('name', 'Mètre carré')->first();
        $kg = Unit::where('name', 'Kilogramme')->first();

        $templates = [
            // ── Matières premières ──────────────────────────────────────
            ['code' => 'mp-tissu-coton', 'name' => 'Tissu coton', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metre?->id, 'default_attributes' => ['composition' => '100% coton', 'gsm_min' => null, 'largeur_laize_cm' => null]],
            ['code' => 'mp-tissu-polyester', 'name' => 'Tissu polyester', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metre?->id, 'default_attributes' => ['composition' => '100% polyester', 'gsm_min' => null, 'largeur_laize_cm' => null]],
            ['code' => 'mp-tissu-fonctionnel', 'name' => 'Tissu avec fonctionnalité (respirant/imperméable/anti-UV)', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metre?->id, 'default_attributes' => ['fonctionnalite' => null, 'certification' => null]],
            ['code' => 'mp-cuir', 'name' => 'Cuir', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metreCarre?->id, 'default_attributes' => ['type_cuir' => null, 'epaisseur_mm' => null]],
            ['code' => 'mp-velcro', 'name' => 'Velcro (bande auto-agrippante)', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metre?->id, 'default_attributes' => ['largeur_mm' => null, 'couleur' => null]],
            ['code' => 'mp-papier-doublure', 'name' => 'Papier pour doublure', 'family' => 'matiere_premiere', 'category_id' => $matieresPremieres->id, 'unit_id' => $metre?->id, 'default_attributes' => ['type' => 'thermocollant/tissé', 'grammage_g' => null]],

            // ── Accessoires ──────────────────────────────────────────────
            ['code' => 'ac-boutons', 'name' => 'Boutons', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $piece?->id, 'default_attributes' => ['diametre_mm' => null, 'matiere' => null]],
            ['code' => 'ac-fermetures', 'name' => 'Fermetures éclair (zip)', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $piece?->id, 'default_attributes' => ['longueur_cm' => null, 'type' => null]],
            ['code' => 'ac-fil', 'name' => 'Fil à coudre', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $kg?->id, 'default_attributes' => ['composition' => null, 'couleur' => null]],
            ['code' => 'ac-etiquettes', 'name' => 'Étiquettes (marque/composition/entretien)', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $piece?->id, 'default_attributes' => ['type' => null]],
            ['code' => 'ac-elastique', 'name' => 'Élastique', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $metre?->id, 'default_attributes' => ['largeur_mm' => null]],
            ['code' => 'ac-rivets', 'name' => 'Rivets et œillets', 'family' => 'accessoire', 'category_id' => $accessoires->id, 'unit_id' => $piece?->id, 'default_attributes' => ['diametre_mm' => null]],

            // ── Vêtements semi-finis (productions spécifiques) ─────────
            ['code' => 'sf-coupe-assemblee', 'name' => 'Vêtement semi-fini — coupe assemblée', 'family' => 'semi_fini', 'category_id' => $semiFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['etape' => 'coupe et assemblage terminés, avant finition']],
            ['code' => 'sf-pret-finition', 'name' => 'Vêtement semi-fini — prêt pour finition/broderie', 'family' => 'semi_fini', 'category_id' => $semiFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['etape' => 'en attente de finition (broderie, teinture, lavage)']],

            // ── Produits finis ───────────────────────────────────────────
            ['code' => 'pf-tshirt', 'name' => 'T-shirt', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['gsm_min' => 250, 'taille' => null, 'couleur' => null]],
            ['code' => 'pf-sweatshirt', 'name' => 'Sweatshirt', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['gsm_min' => 250, 'taille' => null, 'couleur' => null]],
            ['code' => 'pf-chemise', 'name' => 'Chemise', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['gsm_min' => 200, 'taille' => null, 'couleur' => null]],
            ['code' => 'pf-polo', 'name' => 'Polo', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['gsm_min' => 220, 'taille' => null, 'couleur' => null]],
            ['code' => 'pf-pantalon', 'name' => 'Pantalon', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['taille' => null, 'couleur' => null]],
            ['code' => 'pf-veste', 'name' => 'Veste', 'family' => 'produit_fini', 'category_id' => $produitsFinis->id, 'unit_id' => $piece?->id, 'default_attributes' => ['taille' => null, 'couleur' => null]],
        ];

        foreach ($templates as $template) {
            ProductTemplate::firstOrCreate(
                ['code' => $template['code']],
                [...$template, 'is_active' => true]
            );
        }
    }
}
