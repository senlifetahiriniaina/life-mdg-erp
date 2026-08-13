<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Models\CustomsDeclaration;
use Modules\Logistics\Models\HsCode;

/**
 * Customs clearance service.
 *
 * Africa First:
 *  - OHADA duty account Cl.6251 (Droits de douane)
 *  - ECOWAS/CEDEAO, CEMAC, COMESA preferential rates
 *  - 17 OHADA member countries
 */
class CustomsService
{
    private const OHADA_COUNTRIES = [
        'BJ', 'BF', 'CM', 'CF', 'KM', 'CG', 'CD', 'CI', 'GA',
        'GN', 'GW', 'GQ', 'ML', 'NE', 'SN', 'TD', 'TG',
    ];

    private const ECOWAS_COUNTRIES = [
        'BJ', 'BF', 'CV', 'CI', 'GM', 'GH', 'GN', 'GW', 'LR',
        'ML', 'MR', 'NE', 'NG', 'SL', 'SN', 'TG',
    ];

    private const CEMAC_COUNTRIES = ['CM', 'CF', 'CG', 'CD', 'GA', 'TD', 'GQ'];

    private const COMESA_COUNTRIES = [
        'BI', 'KM', 'CD', 'EG', 'ER', 'ET', 'KE', 'LY', 'MG',
        'MW', 'MU', 'RW', 'SC', 'SD', 'SZ', 'TZ', 'UG', 'ZM', 'ZW',
    ];

    /** ECOWAS Common External Tariff rates by 4-digit HS prefix */
    private const ECOWAS_RATES_BY_HS_PREFIX = [
        '0901' => 5.0, '0902' => 5.0, '1006' => 0.0, '1701' => 5.0,
        '5208' => 10.0, '6105' => 20.0, '6203' => 20.0, '6302' => 20.0,
        '7213' => 10.0, '2523' => 5.0, '2710' => 10.0,
        '8471' => 0.0, '8517' => 0.0, '8708' => 10.0,
    ];

    private function generateReference(int $companyId): string
    {
        $year  = Carbon::now()->year;
        $count = CustomsDeclaration::where('company_id', $companyId)
            ->whereYear('created_at', $year)->count() + 1;

        return sprintf('CUST-%d-%04d', $year, $count);
    }

    public function createDeclaration(array $data): CustomsDeclaration
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['reference'] = $this->generateReference((int) $data['company_id']);
        $data['status']    = 'draft';
        $data['currency']  = $data['currency'] ?? 'XOF';

        $declaration = CustomsDeclaration::create($data);

        foreach ($items as $item) {
            $item['declaration_id'] = $declaration->id;
            if (empty($item['total_value'])) {
                $item['total_value'] = (float) ($item['qty'] ?? 1) * (float) ($item['unit_value'] ?? 0);
            }
            $declaration->items()->create($item);
        }

        if (! empty($items)) {
            $declaration->update(['total_value' => $declaration->items()->sum('total_value')]);
        }

        return $declaration->load('items');
    }

    /**
     * Calculate import duties + VAT per item.
     * Africa First: OHADA account Cl.6251 — Droits de douane.
     *
     * @return array<string, mixed>
     */
    public function calculateDuties(int $declarationId): array
    {
        $declaration = CustomsDeclaration::with('items')->findOrFail($declarationId);
        $destCountry = $declaration->country_of_destination;

        $itemResults = [];
        $totalDuties = 0.0;
        $totalVat    = 0.0;

        foreach ($declaration->items as $item) {
            $rates    = $this->getAfricanDutyRates($item->hs_code ?? '', $destCountry);
            $dutyRate = $rates['rate'];

            if ($dutyRate === 0.0 && $item->hs_code) {
                $cat      = HsCode::where('code', $item->hs_code)->first();
                $dutyRate = $cat ? (float) $cat->duty_rate_default : 0.0;
            }

            $itemDuties   = (float) $item->total_value * ($dutyRate / 100);
            $itemVat      = ((float) $item->total_value + $itemDuties) * (18.0 / 100);
            $totalDuties += $itemDuties;
            $totalVat    += $itemVat;

            $itemResults[] = [
                'item_id'     => $item->id,
                'description' => $item->description,
                'hs_code'     => $item->hs_code,
                'total_value' => (float) $item->total_value,
                'duty_rate'   => $dutyRate,
                'duties'      => round($itemDuties, 2),
                'vat'         => round($itemVat, 2),
                'regime'      => $rates['regime'],
            ];
        }

        $declaration->update([
            'duties_amount' => round($totalDuties, 2),
            'vat_amount'    => round($totalVat, 2),
        ]);

        return [
            'items'         => $itemResults,
            'totals'        => [
                'duties'      => round($totalDuties, 2),
                'vat'         => round($totalVat, 2),
                'other_fees'  => (float) $declaration->other_fees,
                'grand_total' => round($totalDuties + $totalVat + (float) $declaration->other_fees, 2),
            ],
            'ohada_account' => '6251',
            'ohada_label'   => 'Droits de douane et assimiles',
            'currency'      => $declaration->currency,
        ];
    }

    public function submit(int $id): CustomsDeclaration
    {
        $declaration = CustomsDeclaration::findOrFail($id);

        if (! in_array($declaration->status, ['draft', 'rejected'])) {
            throw new \InvalidArgumentException("Cannot submit a declaration with status '{$declaration->status}'.");
        }

        $year  = Carbon::now()->year;
        $count = CustomsDeclaration::whereYear('submitted_at', $year)->count() + 1;

        $declaration->update([
            'status'            => 'submitted',
            'submission_number' => sprintf('SUB-%d-%06d', $year, $count),
            'submitted_at'      => now(),
        ]);

        return $declaration->fresh();
    }

    /**
     * Mark declaration as cleared. OHADA journal entry reference generated.
     */
    public function clear(int $id, array $data): CustomsDeclaration
    {
        $declaration = CustomsDeclaration::findOrFail($id);

        if (! in_array($declaration->status, ['submitted', 'under_review', 'approved'])) {
            throw new \InvalidArgumentException("Cannot clear a declaration with status '{$declaration->status}'.");
        }

        $declaration->update([
            'status'        => 'cleared',
            'duties_amount' => $data['duties_paid'] ?? $declaration->duties_amount,
            'vat_amount'    => $data['vat_paid']    ?? $declaration->vat_amount,
            'other_fees'    => $data['other_fees']  ?? $declaration->other_fees,
            'cleared_at'    => now(),
            'notes'         => trim($declaration->notes . "\n[CLEARED] OHADA Cpt.6251 — " . now()->toDateString()),
        ]);

        return $declaration->fresh();
    }

    /**
     * @return array<int, mixed>
     */
    public function getPendingDeclarations(int $companyId): array
    {
        return CustomsDeclaration::where('company_id', $companyId)
            ->whereIn('status', ['draft', 'submitted', 'under_review'])
            ->with('items')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * AI-assisted HS code suggestion. Graceful fallback to 50-code static table.
     *
     * @return array{code: string, description_fr: string, description_en: string, confidence: string, source: string}
     */
    public function getHsCode(string $productDescription): array
    {
        $apiKey = config('services.anthropic.key');

        if ($apiKey) {
            try {
                $result = $this->askClaudeForHsCode($productDescription, $apiKey);
                if ($result !== null) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::warning('CustomsService: Claude HS lookup failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->staticHsCodeLookup($productDescription);
    }

    private function askClaudeForHsCode(string $description, string $apiKey): ?array
    {
        $systemPrompt = 'You are a customs specialist for West and Central African trade (ECOWAS/CEMAC zone). '
            . 'Given a product description, return the best matching HS code (4-6 digits). '
            . 'Respond ONLY in JSON: {"code":"XXXX.XX","description_fr":"...","description_en":"...","confidence":"high|medium|low"}';

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 200,
            'system'     => [
                ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
            ],
            'messages' => [['role' => 'user', 'content' => "Product: {$description}"]],
        ]);

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('content.0.text', '');

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $data = json_decode($matches[0], true);
            if (is_array($data) && isset($data['code'])) {
                return array_merge($data, ['source' => 'claude']);
            }
        }

        return null;
    }

    private function staticHsCodeLookup(string $description): array
    {
        $desc    = strtolower($description);
        $catalog = $this->getStaticHsCatalog();

        foreach ($catalog as $entry) {
            foreach ($entry['keywords'] as $kw) {
                if (str_contains($desc, $kw)) {
                    return [
                        'code'           => $entry['code'],
                        'description_fr' => $entry['description_fr'],
                        'description_en' => $entry['description_en'],
                        'confidence'     => 'medium',
                        'source'         => 'static',
                    ];
                }
            }
        }

        return [
            'code'           => '9999.99',
            'description_fr' => 'Code non determine',
            'description_en' => 'Undetermined code',
            'confidence'     => 'low',
            'source'         => 'static',
        ];
    }

    /**
     * Required documents per incoterm + destination country.
     *
     * @return array{required: string[], recommended: string[], country_specific: string[], incoterm: string, dest_country: string}
     */
    public function getDocumentChecklist(string $incoterm, string $destCountry): array
    {
        $required = ['Facture commerciale', 'Liste de colisage'];

        $required = array_merge($required, match (strtoupper($incoterm)) {
            'FOB', 'CFR', 'CIF' => ['Connaissement maritime (B/L)', "Declaration d'exportation"],
            'EXW'               => ["Bon de commande acheteur", "Preuve d'enlevement"],
            'DDP', 'DAP'        => ["Declaration d'importation", 'Bon de livraison'],
            'FCA'               => ['LTA ou CMR', "Declaration d'exportation"],
            default             => ['Document de transport'],
        });

        if (in_array(strtoupper($incoterm), ['CIF', 'CIP'])) {
            $required[] = "Certificat d'assurance";
        }

        if (in_array(strtoupper($destCountry), self::ECOWAS_COUNTRIES)) {
            $required[] = "Certificat d'origine CEDEAO (Form A)";
        }

        if (in_array(strtoupper($destCountry), self::CEMAC_COUNTRIES)) {
            $required[] = "Certificat d'origine CEMAC";
        }

        return [
            'required'         => array_unique($required),
            'recommended'      => ["Certificat d'inspection (SGS/Bureau Veritas)", 'Fiche de donnees de securite (MSDS)'],
            'country_specific' => $this->getCountrySpecificDocs($destCountry),
            'incoterm'         => strtoupper($incoterm),
            'dest_country'     => strtoupper($destCountry),
        ];
    }

    private function getCountrySpecificDocs(string $country): array
    {
        return match (strtoupper($country)) {
            'SN' => ['Declaration en douane SYDONIA', 'Attestation de conformite BSDA'],
            'CI' => ['Titre importation CEPICI', 'Bordereau de suivi cargaison (BSC)'],
            'CM' => ['Declaration ASYCUDA', 'Bordereau de suivi cargaison (BSC)'],
            'NG' => ['Form M (pre-import approval)', 'Combined Certificate of Value and Origin'],
            'GH' => ['Customs Declaration (CEPS)', 'Ghana Standards Board certificate'],
            'KE' => ['Import Declaration Form (IDF)', 'Pre-Export Verification of Conformity (PVoC)'],
            'MG' => ['Declaration SYDONIA (USID)', "Certificat d'origine"],
            'MA' => ['Declaration unique des marchandises (DUM)', 'Certificat origine EUR.1'],
            'DE', 'FR', 'GB', 'BE', 'NL' => ['Declaration importation UE (SAD)', 'Certificat EUR.1 ou REX'],
            'CN' => ['Customs Declaration', 'CIQ Certificate'],
            'IN' => ['Bill of Entry', 'Import Export Code (IEC)'],
            default => [],
        };
    }

    /**
     * African duty rates (ECOWAS > CEMAC > COMESA > MFN default).
     *
     * @return array{rate: float, regime: string, notes: string}
     */
    public function getAfricanDutyRates(string $hsCode, string $destCountry): array
    {
        $prefix4 = substr($hsCode, 0, 4);
        $dest    = strtoupper($destCountry);

        if (in_array($dest, self::ECOWAS_COUNTRIES)) {
            $rate = self::ECOWAS_RATES_BY_HS_PREFIX[$prefix4] ?? null;
            if ($rate !== null) {
                return ['rate' => $rate, 'regime' => 'ECOWAS/CEDEAO CET', 'notes' => 'Tarif Exterieur Commun CEDEAO'];
            }
        }

        if (in_array($dest, self::CEMAC_COUNTRIES)) {
            return ['rate' => 10.0, 'regime' => 'CEMAC TEC', 'notes' => 'Tarif Exterieur Commun CEMAC'];
        }

        if (in_array($dest, self::COMESA_COUNTRIES)) {
            return ['rate' => 0.0, 'regime' => 'COMESA FTA', 'notes' => 'Zone de libre-echange COMESA — taux preferentiel 0%'];
        }

        $catalogue = HsCode::where('code', $hsCode)->first();
        $rate      = $catalogue ? (float) $catalogue->duty_rate_default : 20.0;

        return ['rate' => $rate, 'regime' => 'MFN (taux general)', 'notes' => 'Taux nation la plus favorisee'];
    }

    /** @return array<int, array{code: string, description_fr: string, description_en: string, keywords: string[]}> */
    private function getStaticHsCatalog(): array
    {
        return [
            ['code' => '5208.11', 'description_fr' => 'Tissus de coton ecru', 'description_en' => 'Unbleached cotton fabric', 'keywords' => ['coton', 'tissu', 'cotton', 'fabric', 'woven']],
            ['code' => '6105.10', 'description_fr' => 'Chemises en coton', 'description_en' => 'Cotton shirts', 'keywords' => ['chemise', 'shirt', 'polo', 't-shirt']],
            ['code' => '6203.41', 'description_fr' => 'Pantalons en coton', 'description_en' => 'Cotton trousers', 'keywords' => ['pantalon', 'trousers', 'pants', 'jean', 'denim']],
            ['code' => '6302.21', 'description_fr' => 'Linge de lit en coton', 'description_en' => 'Cotton bed linen', 'keywords' => ['drap', 'housse', 'linge de lit', 'bed linen', 'bedding']],
            ['code' => '6110.20', 'description_fr' => 'Pulls en coton', 'description_en' => 'Cotton pullovers', 'keywords' => ['pull', 'jersey', 'pullover', 'sweater']],
            ['code' => '1006.30', 'description_fr' => 'Riz blanchi', 'description_en' => 'Milled rice', 'keywords' => ['riz', 'rice']],
            ['code' => '1701.14', 'description_fr' => 'Sucre de canne brut', 'description_en' => 'Raw cane sugar', 'keywords' => ['sucre', 'sugar', 'canne']],
            ['code' => '0901.11', 'description_fr' => 'Cafe non torrefie', 'description_en' => 'Coffee, not roasted', 'keywords' => ['cafe', 'coffee', 'arabica', 'robusta']],
            ['code' => '0902.10', 'description_fr' => 'The vert', 'description_en' => 'Green tea', 'keywords' => ['the', 'tea', 'vert', 'green']],
            ['code' => '2402.20', 'description_fr' => 'Cigarettes', 'description_en' => 'Cigarettes', 'keywords' => ['cigarette', 'tabac', 'tobacco']],
            ['code' => '1511.10', 'description_fr' => 'Huile de palme brute', 'description_en' => 'Crude palm oil', 'keywords' => ['huile palme', 'palm oil']],
            ['code' => '8471.30', 'description_fr' => 'Ordinateurs portables', 'description_en' => 'Laptops', 'keywords' => ['ordinateur', 'laptop', 'computer', 'pc', 'macbook']],
            ['code' => '8517.12', 'description_fr' => 'Telephones mobiles', 'description_en' => 'Mobile phones', 'keywords' => ['telephone', 'mobile', 'smartphone', 'phone', 'iphone', 'android']],
            ['code' => '8517.62', 'description_fr' => 'Routeurs et switchs', 'description_en' => 'Routers and switches', 'keywords' => ['routeur', 'router', 'switch', 'reseau', 'network']],
            ['code' => '8528.72', 'description_fr' => 'Ecrans plats', 'description_en' => 'Flat panel displays', 'keywords' => ['ecran', 'monitor', 'screen', 'display', 'television', 'tv']],
            ['code' => '8708.29', 'description_fr' => 'Pieces carrosserie', 'description_en' => 'Vehicle body parts', 'keywords' => ['carrosserie', 'body part', 'bumper', 'pare-choc']],
            ['code' => '8708.99', 'description_fr' => 'Autres pieces auto', 'description_en' => 'Other auto parts', 'keywords' => ['pieces auto', 'auto parts', 'spare parts', 'pieces detachees']],
            ['code' => '8703.23', 'description_fr' => 'Voitures particulieres', 'description_en' => 'Passenger cars 1500-3000cc', 'keywords' => ['voiture', 'car', 'vehicule', 'automobile']],
            ['code' => '8704.21', 'description_fr' => 'Camionnettes diesel', 'description_en' => 'Diesel pickup trucks', 'keywords' => ['camionnette', 'pickup', 'utilitaire', 'van']],
            ['code' => '7213.10', 'description_fr' => 'Barres en fer (ronds a beton)', 'description_en' => 'Iron/steel bars (rebar)', 'keywords' => ['barre fer', 'fer', 'acier', 'steel bar', 'rebar', 'beton']],
            ['code' => '2523.29', 'description_fr' => 'Ciment portland', 'description_en' => 'Portland cement', 'keywords' => ['ciment', 'cement', 'portland', 'beton', 'concrete']],
            ['code' => '2710.19', 'description_fr' => 'Huiles de petrole (gasoil, kerosene)', 'description_en' => 'Petroleum oils (diesel, kerosene)', 'keywords' => ['gasoil', 'diesel', 'kerosene', 'fuel', 'petrole']],
            ['code' => '8541.40', 'description_fr' => 'Panneaux solaires', 'description_en' => 'Solar panels', 'keywords' => ['solaire', 'solar', 'photovoltaique', 'panneau solaire', 'pv']],
            ['code' => '8507.60', 'description_fr' => 'Batteries lithium', 'description_en' => 'Lithium batteries', 'keywords' => ['batterie lithium', 'lithium battery', 'accumulateur', 'battery']],
            ['code' => '3004.90', 'description_fr' => 'Medicaments', 'description_en' => 'Medicaments', 'keywords' => ['medicament', 'medicine', 'drug', 'pharmaceutical', 'comprime', 'sirop']],
            ['code' => '9018.90', 'description_fr' => 'Instruments medicaux', 'description_en' => 'Medical instruments', 'keywords' => ['medical', 'instrument medical', 'echographe', 'scanner']],
            ['code' => '8432.80', 'description_fr' => 'Machines agricoles', 'description_en' => 'Agricultural machinery', 'keywords' => ['machine agricole', 'agricultural machine', 'tracteur', 'tractor', 'semoir']],
            ['code' => '3102.10', 'description_fr' => 'Uree (engrais)', 'description_en' => 'Urea fertilizer', 'keywords' => ['uree', 'urea', 'engrais', 'fertilizer']],
            ['code' => '3401.11', 'description_fr' => 'Savon de toilette', 'description_en' => 'Toilet soap', 'keywords' => ['savon', 'soap', 'toilette']],
            ['code' => '3304.99', 'description_fr' => 'Produits de beaute', 'description_en' => 'Beauty products', 'keywords' => ['cosmetique', 'cosmetic', 'maquillage', 'makeup', 'creme', 'cream']],
        ];
    }
}
