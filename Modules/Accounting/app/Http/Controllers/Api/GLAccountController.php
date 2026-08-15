<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreGLAccountRequest;
use Modules\Accounting\Http\Requests\UpdateGLAccountRequest;
use Modules\Accounting\Http\Resources\GLAccountResource;
use Modules\Accounting\Models\GLAccount;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting
 *
 * Manage GLAccount resources in Accounting module.
 */
class GLAccountController extends Controller
{
    public function __construct(protected AccountingService $service) {}

    public function index(Request $request)
    {
        $type = $request->query('type');
        $perPage = $request->query('per_page', 15);

        if ($type) {
            $accounts = $this->service->getGLAccountsByType($type, $perPage);
        } else {
            $accounts = $this->service->getAllGLAccounts($perPage);
        }

        return GLAccountResource::collection($accounts);
    }

    public function store(StoreGLAccountRequest $request)
    {
        $account = $this->service->createGLAccount($request->validated());

        return (new GLAccountResource($account))->response()->setStatusCode(201);
    }

    public function show(GLAccount $account)
    {
        $account->load('journalEntries');

        return new GLAccountResource($account);
    }

    public function update(UpdateGLAccountRequest $request, GLAccount $account)
    {
        $updated = $this->service->updateGLAccount($account, $request->validated());

        return new GLAccountResource($updated);
    }

    public function destroy(GLAccount $account)
    {
        $account->delete();

        return response()->noContent();
    }
}
