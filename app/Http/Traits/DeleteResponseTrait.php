<?php

declare(strict_types=1);

namespace App\Http\Traits;

trait DeleteResponseTrait
{
    protected function deleteResponse($resource = null, int $statusCode = 204)
    {
        if ($resource === null) {
            return response()->noContent();
        }

        return response()->json($resource, $statusCode);
    }
}
