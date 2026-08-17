<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/Profile', ['user' => $request->user()]);
    }
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
        ]);
        $request->user()->fill($validated)->save();
        return back()->with('success', 'Profile updated.');
    }
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|min:8|confirmed',
        ]);

        // OWASP password-reuse prevention: reject any of the user's last N
        // password hashes (config('auth.password_history_limit'), default 5).
        if ($request->user()->wasPasswordUsedBefore($request->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => __('This password has been used recently. Please choose a different one.'),
            ]);
        }

        $hashed = Hash::make($request->password);
        $request->user()->update(['password' => $hashed]);
        $request->user()->recordPasswordHistory($hashed);

        return back()->with('success', 'Password changed.');
    }
}
