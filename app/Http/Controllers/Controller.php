<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Extends Illuminate\Routing\Controller (not just a bare abstract class) so
 * this is an actual strict superset of it, not just a superset in
 * intention. Without this, swapping a controller from
 * Illuminate\Routing\Controller to this class would silently break any
 * $this->middleware()/getMiddleware() call in its constructor — several of
 * the ~125 controllers migrated onto this base class do call it.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
