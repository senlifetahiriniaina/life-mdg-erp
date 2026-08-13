<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Logistics\Models\HsCode;

/**
 * Seed WCO 2022 HS codes (200+ codes covering all 97 chapters).
 * Africa First: ECOWAS CET rates applied where applicable.
 *
 * Loads from database/data/hs_codes.json; falls back to the legacy
 * 50-code inline array when the JSON file is missing.
 */
class HsCodeSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = __DIR__ . '/../data/hs_codes.json';

        if (file_exists($jsonPath)) {
            $this->seedFromJson($jsonPath);
        } else {
            $this->seedFallback();
        }
    }

    private function seedFromJson(string $path): void
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->command?->warn('HsCodeSeeder: could not read JSON file, using fallback.');
            $this->seedFallback();
            return;
        }

        /** @var array<int,array<string,mixed>> $codes */
        $codes = json_decode($raw, true);
        if (!is_array($codes)) {
            $this->command?->warn('HsCodeSeeder: invalid JSON, using fallback.');
            $this->seedFallback();
            return;
        }

        $rows = array_map(fn (array $c) => [
            'code'               => $c['code'],
            'description_fr'     => $c['description_fr'],
            'description_en'     => $c['description_en'],
            'duty_rate_default'  => $c['duty_rate_default'] ?? 0.0,
            'vat_applicable'     => $c['vat_applicable'] ?? false,
            'requires_license'   => $c['requires_license'] ?? false,
            'notes'              => $c['notes'] ?? null,
            'chapter'            => $c['chapter'] ?? null,
            'section'            => $c['section'] ?? null,
            'unit'               => $c['unit'] ?? null,
        ], $codes);

        // Upsert in chunks to avoid oversized queries
        foreach (array_chunk($rows, 100) as $chunk) {
            HsCode::upsert(
                $chunk,
                ['code'],
                ['description_fr', 'description_en', 'duty_rate_default', 'vat_applicable', 'requires_license', 'notes', 'chapter', 'section', 'unit']
            );
        }

        $this->command?->info(sprintf('HsCodeSeeder: %d HS codes seeded from JSON.', count($rows)));
    }

    private function seedFallback(): void
    {
        $codes = [
            ['code' => '5208.11', 'description_fr' => 'Tissus de coton ecru, armure toile', 'description_en' => 'Unbleached plain weave cotton', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.2 10%', 'chapter' => '52', 'section' => 'XI', 'unit' => 'm2'],
            ['code' => '6105.10', 'description_fr' => 'Chemises en coton pour hommes', 'description_en' => 'Cotton shirts for men', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.3 20%', 'chapter' => '61', 'section' => 'XI', 'unit' => 'p/st'],
            ['code' => '6203.41', 'description_fr' => 'Pantalons en coton pour hommes', 'description_en' => "Men's cotton trousers", 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '62', 'section' => 'XI', 'unit' => 'p/st'],
            ['code' => '6204.61', 'description_fr' => 'Pantalons en coton pour femmes', 'description_en' => "Women's cotton trousers", 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '62', 'section' => 'XI', 'unit' => 'p/st'],
            ['code' => '6302.21', 'description_fr' => 'Linge de lit en coton imprime', 'description_en' => 'Printed cotton bed linen', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '63', 'section' => 'XI', 'unit' => 'kg'],
            ['code' => '6302.91', 'description_fr' => 'Linge de toilette en coton', 'description_en' => 'Cotton towels', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '63', 'section' => 'XI', 'unit' => 'kg'],
            ['code' => '6110.20', 'description_fr' => 'Chandails et pulls en coton', 'description_en' => 'Cotton jerseys and pullovers', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '61', 'section' => 'XI', 'unit' => 'p/st'],
            ['code' => '1006.30', 'description_fr' => 'Riz semi-blanchi ou blanchi', 'description_en' => 'Semi-milled or wholly milled rice', 'duty_rate_default' => 0.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.0 0% bien essentiel', 'chapter' => '10', 'section' => 'II', 'unit' => 'kg'],
            ['code' => '1701.14', 'description_fr' => 'Sucre de canne brut en phase solide', 'description_en' => 'Raw cane sugar in solid form', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.1 5%', 'chapter' => '17', 'section' => 'IV', 'unit' => 'kg'],
            ['code' => '0901.11', 'description_fr' => 'Cafe non torrefie non decafeine', 'description_en' => 'Coffee, not roasted, not decaffeinated', 'duty_rate_default' => 5.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => 'Exportation CI/CM/ET', 'chapter' => '09', 'section' => 'II', 'unit' => 'kg'],
            ['code' => '0902.10', 'description_fr' => 'The vert en emballages max 3 kg', 'description_en' => 'Green tea in packages max 3 kg', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '09', 'section' => 'II', 'unit' => 'kg'],
            ['code' => '0902.30', 'description_fr' => 'The noir fermente', 'description_en' => 'Black fermented tea', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '09', 'section' => 'II', 'unit' => 'kg'],
            ['code' => '2402.20', 'description_fr' => 'Cigarettes contenant du tabac', 'description_en' => 'Cigarettes containing tobacco', 'duty_rate_default' => 35.0, 'vat_applicable' => 1, 'requires_license' => 1, 'notes' => 'ECOWAS CET Cat.4 35% + accises', 'chapter' => '24', 'section' => 'IV', 'unit' => '1000 p/st'],
            ['code' => '1511.10', 'description_fr' => 'Huile de palme brute', 'description_en' => 'Crude palm oil', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '15', 'section' => 'III', 'unit' => 'kg'],
            ['code' => '1507.10', 'description_fr' => 'Huile de soja brute', 'description_en' => 'Crude soya-bean oil', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '15', 'section' => 'III', 'unit' => 'kg'],
            ['code' => '1902.30', 'description_fr' => 'Autres pates alimentaires non cuites', 'description_en' => 'Other uncooked pasta', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '19', 'section' => 'IV', 'unit' => 'kg'],
            ['code' => '0803.90', 'description_fr' => 'Bananes fraiches autres que plantains', 'description_en' => 'Fresh bananas other than plantains', 'duty_rate_default' => 10.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => null, 'chapter' => '08', 'section' => 'II', 'unit' => 'kg'],
            ['code' => '2106.90', 'description_fr' => 'Preparations alimentaires non denomme es ailleurs', 'description_en' => 'Food preparations NES', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '21', 'section' => 'IV', 'unit' => 'kg'],
            ['code' => '8471.30', 'description_fr' => 'Machines de traitement automatique portables (laptops)', 'description_en' => 'Portable automatic data processing machines (laptops)', 'duty_rate_default' => 0.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.0 IT Agreement', 'chapter' => '84', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8471.41', 'description_fr' => 'Machines de traitement de donnees autres', 'description_en' => 'Other data processing machines', 'duty_rate_default' => 0.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '84', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8517.12', 'description_fr' => 'Telephones pour reseaux cellulaires smartphones', 'description_en' => 'Telephones for cellular networks smartphones', 'duty_rate_default' => 0.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'Exonere dans la plupart des pays CEDEAO', 'chapter' => '85', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8517.62', 'description_fr' => 'Appareils de reception transmission (routeurs)', 'description_en' => 'Routers switches and hubs', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '85', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8528.72', 'description_fr' => 'Moniteurs et ecrans plats', 'description_en' => 'Flat panel displays and monitors', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '85', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8544.42', 'description_fr' => 'Conducteurs electriques isoles 1000 V max', 'description_en' => 'Insulated electric conductors max 1000V', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '85', 'section' => 'XVI', 'unit' => 'kg'],
            ['code' => '8708.29', 'description_fr' => 'Parties pour carrosserie sauf pare-chocs', 'description_en' => 'Parts for vehicle bodies other than bumpers', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '87', 'section' => 'XVII', 'unit' => 'kg'],
            ['code' => '8708.80', 'description_fr' => 'Amortisseurs et suspensions', 'description_en' => 'Suspension shock-absorbers', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '87', 'section' => 'XVII', 'unit' => 'kg'],
            ['code' => '8708.99', 'description_fr' => 'Autres pieces et accessoires de vehicules', 'description_en' => 'Other parts and accessories for vehicles', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '87', 'section' => 'XVII', 'unit' => 'kg'],
            ['code' => '8703.23', 'description_fr' => 'Voitures particulieres 1500 a 3000 cm3', 'description_en' => 'Passenger vehicles 1500-3000cc engine', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '87', 'section' => 'XVII', 'unit' => 'p/st'],
            ['code' => '8704.21', 'description_fr' => 'Vehicules transport marchandises diesel moins 5T', 'description_en' => 'Diesel trucks payload less than 5T', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '87', 'section' => 'XVII', 'unit' => 'p/st'],
            ['code' => '7213.10', 'description_fr' => 'Fil machine en fer ou acier ronds a beton', 'description_en' => 'Iron steel wire rod in coils rebar', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'ECOWAS CET Cat.2', 'chapter' => '72', 'section' => 'XV', 'unit' => 'kg'],
            ['code' => '7214.20', 'description_fr' => 'Barres en acier laminées a chaud deformees', 'description_en' => 'Deformed hot-rolled steel bars', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '72', 'section' => 'XV', 'unit' => 'kg'],
            ['code' => '2523.29', 'description_fr' => 'Ciment portland autres', 'description_en' => 'Portland cement other', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'Matiere premiere taux reduit', 'chapter' => '25', 'section' => 'V', 'unit' => 'kg'],
            ['code' => '6810.11', 'description_fr' => 'Carreaux et dalles en ceramique', 'description_en' => 'Ceramic tiles and slabs', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '68', 'section' => 'XIII', 'unit' => 'm2'],
            ['code' => '2710.19', 'description_fr' => 'Autres huiles de petrole gasoil kerosene', 'description_en' => 'Petroleum oils diesel kerosene', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 1, 'notes' => 'Licence importation obligatoire CEDEAO', 'chapter' => '27', 'section' => 'V', 'unit' => 'l'],
            ['code' => '2711.19', 'description_fr' => 'Gaz de petrole liquefies GPL propane butane', 'description_en' => 'Liquefied petroleum gases LPG', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '27', 'section' => 'V', 'unit' => 'kg'],
            ['code' => '8507.60', 'description_fr' => 'Accumulateurs electriques au lithium', 'description_en' => 'Lithium-ion accumulators', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '85', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '8541.40', 'description_fr' => 'Panneaux solaires photovoltaiques', 'description_en' => 'Photovoltaic solar panels', 'duty_rate_default' => 0.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => 'Exonere CEDEAO pour acces energie', 'chapter' => '85', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '3004.90', 'description_fr' => 'Medicaments pour vente au detail autres', 'description_en' => 'Medicaments for retail sale other', 'duty_rate_default' => 0.0, 'vat_applicable' => 0, 'requires_license' => 1, 'notes' => 'AMM obligatoire exonere droits douane', 'chapter' => '30', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3005.10', 'description_fr' => 'Adhesifs en bandes et pansements', 'description_en' => 'Adhesive dressings and bandages', 'duty_rate_default' => 5.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => null, 'chapter' => '30', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '9018.90', 'description_fr' => 'Instruments medicaux autres', 'description_en' => 'Medical instruments other', 'duty_rate_default' => 5.0, 'vat_applicable' => 0, 'requires_license' => 1, 'notes' => 'Homologation sanitaire requise', 'chapter' => '90', 'section' => 'XVIII', 'unit' => 'p/st'],
            ['code' => '8432.80', 'description_fr' => 'Machines et appareils pour agriculture autres', 'description_en' => 'Agricultural machinery other', 'duty_rate_default' => 5.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'Taux reduit soutien agriculture', 'chapter' => '84', 'section' => 'XVI', 'unit' => 'p/st'],
            ['code' => '3102.10', 'description_fr' => 'Uree engrais', 'description_en' => 'Urea fertilizer', 'duty_rate_default' => 5.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => null, 'chapter' => '31', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3808.91', 'description_fr' => 'Insecticides pour vente au detail', 'description_en' => 'Insecticides for retail sale', 'duty_rate_default' => 10.0, 'vat_applicable' => 1, 'requires_license' => 1, 'notes' => 'Homologation phytosanitaire requise', 'chapter' => '38', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3401.11', 'description_fr' => 'Savons et produits tensio-actifs de toilette', 'description_en' => 'Toilet soap in bars', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '34', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3305.10', 'description_fr' => 'Shampoings pour la chevelure', 'description_en' => 'Shampoos', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '33', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3304.99', 'description_fr' => 'Preparations pour soins de la peau autres', 'description_en' => 'Skin care preparations other', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '33', 'section' => 'VI', 'unit' => 'kg'],
            ['code' => '3923.21', 'description_fr' => 'Sacs et sachets en polyethylene', 'description_en' => 'Polyethylene bags and sacks', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'Interdits ou taxes dans plusieurs pays africains', 'chapter' => '39', 'section' => 'VII', 'unit' => 'kg'],
            ['code' => '4819.10', 'description_fr' => 'Boites et caisses en papier ou carton ondule', 'description_en' => 'Cartons and boxes of corrugated paper', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => null, 'chapter' => '48', 'section' => 'X', 'unit' => 'kg'],
            ['code' => '0303.89', 'description_fr' => 'Autres poissons congeles', 'description_en' => 'Other frozen fish', 'duty_rate_default' => 5.0, 'vat_applicable' => 0, 'requires_license' => 1, 'notes' => 'Certificat sanitaire obligatoire', 'chapter' => '03', 'section' => 'I', 'unit' => 'kg'],
            ['code' => '1604.14', 'description_fr' => 'Thons et listaos prepares ou conserves', 'description_en' => 'Prepared or preserved tuna', 'duty_rate_default' => 20.0, 'vat_applicable' => 1, 'requires_license' => 0, 'notes' => 'Transformation locale valorisee', 'chapter' => '16', 'section' => 'IV', 'unit' => 'kg'],
            ['code' => '0805.10', 'description_fr' => 'Oranges fraiches', 'description_en' => 'Fresh oranges', 'duty_rate_default' => 10.0, 'vat_applicable' => 0, 'requires_license' => 0, 'notes' => null, 'chapter' => '08', 'section' => 'II', 'unit' => 'kg'],
        ];

        foreach ($codes as $code) {
            HsCode::updateOrCreate(['code' => $code['code']], $code);
        }

        $this->command?->info(sprintf('HsCodeSeeder: %d HS codes seeded (fallback).', count($codes)));
    }
}
