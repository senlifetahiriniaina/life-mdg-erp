<?php
declare(strict_types=1);
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|min:8|confirmed',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                // OWASP password-reuse prevention: reject any of the user's last N
                // password hashes (config('auth.password_history_limit'), default 5).
                if ($user->wasPasswordUsedBefore($request->password)) {
                    throw ValidationException::withMessages([
                        'password' => __('This password has been used recently. Please choose a different one.'),
                    ]);
                }

                $hashed = Hash::make($request->password);
                $user->forceFill(['password' => $hashed, 'remember_token' => Str::random(60)])->save();
                $user->recordPasswordHistory($hashed);
                event(new PasswordReset($user));
            }
        );
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput()->withErrors(['email' => __($status)]);
    }
}
