<?php

namespace Modules\Integration\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WhbFederationController extends Controller
{
    public function wellKnown(): JsonResponse
    {
        return response()->json([
            'name'    => config('app.name', 'WideHalo ERP'),
            'version' => config('app.version', '1.0.0'),
            'api'     => url('/api/v1'),
            'modules' => [],
        ]);
    }
}
