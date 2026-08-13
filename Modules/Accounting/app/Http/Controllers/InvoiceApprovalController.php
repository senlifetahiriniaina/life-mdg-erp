<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class InvoiceApprovalController extends Controller
{
    public function __call($method, $parameters): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
