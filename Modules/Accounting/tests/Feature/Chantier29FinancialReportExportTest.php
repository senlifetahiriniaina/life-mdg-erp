<?php

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;

/**
 * Chantier 29 — 7-layer verification of the financial-report export
 * machinery (route, controller, view, model, data format, security, RBAC).
 *
 * `ReportingController::exportPdf()`/`exportExcel()` — routed at
 * `financial-reports/{report}/export/{pdf,excel}` — used to be pure stubs
 * returning `{"message":"Export queued."}` JSON with no file ever generated,
 * despite being reachable. Both now delegate to the same real, already-
 * working `OhadaReportService::generateBalanceSheet()`/
 * `generateIncomeStatement()` (Chantier 18) the on-screen `BalanceSheet.vue`/
 * `IncomeStatement.vue` pages already call, so the exported file always
 * matches what the user sees on screen.
 *
 * Every assertion here hits the real HTTP route and inspects the real
 * response bytes (PDF `%PDF` magic / XLSX `PK` zip magic containing the
 * real seeded figure), matching this session's established empirical-
 * verification-over-code-reading methodology.
 */
beforeEach(function () {
    $this->user = actingAsUser('accountant');

    $this->accountClients = ChartOfAccount::factory()->create(['code' => '411', 'name' => 'Clients', 'type' => 'asset']);
    $this->accountVentes  = ChartOfAccount::factory()->create(['code' => '707', 'name' => 'Ventes', 'type' => 'revenue']);

    $entry = JournalEntry::create([
        'entry_number' => 'EXPORT-TEST-1',
        'date'         => now()->toDateString(),
        'description'  => 'Vente test export',
        'status'       => 'posted',
        'currency'     => 'MGA',
    ]);
    $entry->lines()->create(['account_id' => $this->accountClients->id, 'debit' => 654321, 'credit' => 0]);
    $entry->lines()->create(['account_id' => $this->accountVentes->id, 'debit' => 0, 'credit' => 654321]);

    $this->period = now()->format('Y-m');
});

/**
 * Real text-extraction from a DomPDF-generated PDF, verified empirically
 * against a real generated file (`php artisan tinker`, this chantier) before
 * being trusted here. Two stacked encoding layers had to be accounted for,
 * not just the outer PDF envelope:
 *  - DomPDF flate-compresses every content stream (`/Filter /FlateDecode`),
 *    so the rendered figure never appears as a raw substring of the PDF
 *    bytes at all — confirmed the first naive `str_contains()` attempt
 *    always failed even on a real, correctly-rendered PDF.
 *  - DomPDF embeds its fonts with `/Encoding /Identity-H` and an identity
 *    `ToUnicode` CMap (confirmed by inspecting a real generated PDF's own
 *    CMap object) — text is drawn via `(...) TJ`/`Tj` operators whose
 *    literal-string bytes are raw UTF-16BE code units, not ASCII, so even
 *    an inflated stream needs its literal strings decoded as UTF-16BE
 *    before a plain-text search means anything. A first attempt that
 *    searched for `<hex>`-form show-text operands found nothing either —
 *    DomPDF emits `(...)` literal strings, not `<hex>` strings.
 */
function pdfExtractText(string $pdf): string
{
    $text = '';

    if (! preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches)) {
        return $text;
    }

    foreach ($matches[1] as $chunk) {
        $decoded = @gzuncompress($chunk);
        if ($decoded === false) {
            continue;
        }

        $len = strlen($decoded);
        $i   = 0;
        while ($i < $len) {
            if ($decoded[$i] !== '(') {
                $i++;
                continue;
            }
            $depth = 1;
            $j     = $i + 1;
            $buf   = '';
            while ($j < $len && $depth > 0) {
                $c = $decoded[$j];
                if ($c === '\\') {
                    $next = $decoded[$j + 1] ?? '';
                    if ($next === '(' || $next === ')' || $next === '\\') {
                        $buf .= $next;
                        $j   += 2;

                        continue;
                    }
                    $j++;

                    continue;
                }
                if ($c === '(') {
                    $depth++;
                    $buf .= $c;
                    $j++;

                    continue;
                }
                if ($c === ')') {
                    $depth--;
                    $j++;
                    if ($depth === 0) {
                        break;
                    }
                    $buf .= $c;

                    continue;
                }
                $buf .= $c;
                $j++;
            }
            $i = $j;

            if (strlen($buf) % 2 === 0 && $buf !== '') {
                $text .= @mb_convert_encoding($buf, 'UTF-8', 'UTF-16BE');
            }
        }
    }

    return $text;
}

test('balance-sheet PDF export returns a genuine, non-empty PDF with the real seeded figure', function () {
    $response = $this->get("/api/v1/accounting/financial-reports/balance-sheet/export/pdf?period={$this->period}");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');

    $bytes = $response->getContent();
    expect(substr($bytes, 0, 4))->toBe('%PDF');
    expect(strlen($bytes))->toBeGreaterThan(1000);

    // Blade formats with a space thousands-separator ("654 321"), matching
    // BalanceSheet.vue's own fr-MG Intl.NumberFormat convention — not a comma.
    $text = pdfExtractText($bytes);
    expect($text)->toContain('654 321');
    expect($text)->toContain('BILAN');
    expect($text)->toContain('Bilan équilibré');
});

test('income-statement PDF export returns a genuine, non-empty PDF with the real seeded figure', function () {
    $response = $this->get("/api/v1/accounting/financial-reports/income-statement/export/pdf?period={$this->period}");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');

    $bytes = $response->getContent();
    expect(substr($bytes, 0, 4))->toBe('%PDF');
    expect(strlen($bytes))->toBeGreaterThan(1000);

    $text = pdfExtractText($bytes);
    expect($text)->toContain('654 321');
    expect($text)->toContain("Chiffre d'affaires");
});

test('balance-sheet Excel export returns a genuine, openable XLSX with the real seeded figure', function () {
    $response = $this->get("/api/v1/accounting/financial-reports/balance-sheet/export/excel?period={$this->period}");

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $path = sys_get_temp_dir().'/chantier29-bs-'.uniqid().'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        expect(substr(file_get_contents($path), 0, 2))->toBe('PK');

        $zip = new ZipArchive();
        expect($zip->open($path))->toBe(true);
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        expect($shared)->not->toBeNull();
        expect($shared)->toContain('654,321.00');
        expect($shared)->toContain('ACTIF');
        expect($shared)->toContain('PASSIF');
    } finally {
        @unlink($path);
    }
});

test('income-statement Excel export returns a genuine, openable XLSX with the real seeded figure', function () {
    $response = $this->get("/api/v1/accounting/financial-reports/income-statement/export/excel?period={$this->period}");

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $path = sys_get_temp_dir().'/chantier29-is-'.uniqid().'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        expect(substr(file_get_contents($path), 0, 2))->toBe('PK');

        $zip = new ZipArchive();
        expect($zip->open($path))->toBe(true);
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        expect($shared)->not->toBeNull();
        expect($shared)->toContain('654,321.00');
        expect($shared)->toContain('Chiffre d&#039;affaires');
    } finally {
        @unlink($path);
    }
});

test('an unsupported report type 404s instead of silently returning an empty or wrong file', function () {
    $this->get("/api/v1/accounting/financial-reports/cash-flow/export/pdf?period={$this->period}")
        ->assertStatus(404);

    $this->get("/api/v1/accounting/financial-reports/cash-flow/export/excel?period={$this->period}")
        ->assertStatus(404);
});

test('underscore/mixed-case report slugs are normalized to the same real export', function () {
    $response = $this->get("/api/v1/accounting/financial-reports/balance_sheet/export/pdf?period={$this->period}");

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('a role outside the reporting gate is denied both exports', function () {
    actingAsUser('sales-rep');

    $this->get("/api/v1/accounting/financial-reports/balance-sheet/export/pdf?period={$this->period}")
        ->assertStatus(403);

    $this->get("/api/v1/accounting/financial-reports/balance-sheet/export/excel?period={$this->period}")
        ->assertStatus(403);
});

test('an unauthenticated request is rejected', function () {
    \Illuminate\Support\Facades\Auth::forgetGuards();

    $this->getJson("/api/v1/accounting/financial-reports/balance-sheet/export/pdf?period={$this->period}")
        ->assertStatus(401);
});
