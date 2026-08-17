<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DataStoryWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('BI/DataStories/Index');
    }
}
