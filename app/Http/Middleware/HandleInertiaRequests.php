<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Core\Services\ModuleManager;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $enabledModules = [];

        if ($user) {
            try {
                /** @var ModuleManager $manager */
                $manager = app(ModuleManager::class);
                $enabledModules = $manager->enabledModules((string) $user->id)->toArray();
            } catch (\Throwable) {
                $enabledModules = [];
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'locale'     => $user->locale ?? 'en',
                    'avatar'     => $user->avatar,
                    'roles'       => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'enabledModules' => $enabledModules,
            'locale'         => $user?->locale ?? app()->getLocale(),
            'flash'          => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }
}
