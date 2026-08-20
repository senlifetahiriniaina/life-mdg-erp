<?php

namespace Modules\Shared\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    /**
     * Chantier 19 Lot 3: index()/show() queried columns that have never
     * existed on `shared_countries` (`active`, `code`, `ohada_member` — the
     * real ones are `is_ohada` and no active flag at all, confirmed via
     * Schema::getColumnListing) — a bug camouflaged as an empty result
     * rather than a loud SQL error: SQLite (and MySQL's default
     * non-ANSI_QUOTES mode) both silently fall back to treating an
     * unrecognized double-quoted identifier as a plain string literal
     * instead of throwing "unknown column", so `"active" = ?` silently
     * compared the literal string 'active' against the bound value and
     * always evaluated false — confirmed empirically via a real HTTP
     * request. Also confirmed `shared_countries` was never seeded anywhere
     * in the app (same gap already found and fixed for shared_currencies
     * in Chantier 17) — fixed here too, reusing the same real per-country
     * data Modules\Core\Services\SmartDefaultsService::COUNTRIES already
     * defines (not invented), via database/seeders/DefaultDataSeeder.php.
     */
    public function index(Request $request)
    {
        $query = DB::table('shared_countries');
        if ($request->region) {
            $query->where('region', $request->region);
        }
        if ($request->boolean('ohada')) {
            $query->where('is_ohada', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show($code)
    {
        $column = strlen($code) === 2 ? 'iso_alpha2' : 'iso_alpha3';
        $country = DB::table('shared_countries')->where($column, strtoupper($code))->first();
        abort_if(!$country, 404);
        return response()->json(['data' => $country]);
    }

    // Chantier 19 Lot 3: currencies() removed — already confirmed dead
    // (routes/api.php's own comment documents CurrencyController::index()
    // as the real superset that replaced it; grep confirms zero routes/
    // callers left anywhere). It was also carrying a real, previously
    // undetected bug: it queried `where('active', true)`, a column that
    // has never existed on shared_currencies (the real column is
    // is_active) — a guaranteed "no such column" SQL error the moment it
    // was ever actually hit. Deleted rather than fixed, matching this
    // session's established dead-scaffold-code precedent, since it has no
    // route to reach it at all.

    /**
     * Chantier 19 Lot 3: confirmed, documented gap, NOT fixed — `shared_tax_rates`
     * has no migration anywhere in the repo (confirmed via grep), so this
     * throws a real "no such table" error on every call, not the silent
     * empty-result camouflage index()/show() had. Building this out for
     * real means designing a genuinely new concept (date-ranged VAT-rate
     * history per country) rather than fixing a wiring bug — `shared_countries`
     * already carries a flat, always-current `vat_rate` column that
     * SmartDefaultsService::COUNTRIES already exposes per-country, so
     * whether this needs its own time-boxed history table at all is a
     * product decision, left for a future chantier rather than guessed at.
     */
    public function taxRates($code)
    {
        $rates = DB::table('shared_tax_rates')
            ->where('country_code', strtoupper($code))
            ->where('active_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('active_to')->orWhere('active_to', '>=', now());
            })
            ->get();
        return response()->json(['data' => $rates]);
    }
}
