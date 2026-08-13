<?php

namespace Modules\Shared\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('shared_countries')->where('active', true);
        if ($request->region) {
            $query->where('region', $request->region);
        }
        if ($request->ohada) {
            $query->where('ohada_member', true);
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show($code)
    {
        $country = DB::table('shared_countries')->where('code', strtoupper($code))->first();
        abort_if(!$country, 404);
        return response()->json(['data' => $country]);
    }

    public function currencies(Request $request)
    {
        $currencies = DB::table('shared_currencies')->where('active', true)->orderBy('code')->get();
        return response()->json(['data' => $currencies]);
    }

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
