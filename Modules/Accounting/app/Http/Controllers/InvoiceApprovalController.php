<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class InvoiceApprovalController extends Controller
{
    public function __call($method, $parameters): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
