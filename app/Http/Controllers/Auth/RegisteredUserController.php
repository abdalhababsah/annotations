<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email_prefix' => 'required|string|lowercase|alpha_dash|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $request->email_prefix . '@menadevs.io';

        // Validate email uniqueness separately
        $validator = \Illuminate\Support\Facades\Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'email_prefix' => ['The email address has already been taken.'],
            ]);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'is_active' => false,
        ]);

        // Trigger the Registered event to send verification email
        event(new Registered($user));

        // Redirect to login with a status message
        return redirect()->route('login')->with('status', 'Registration successful! Please check your email to verify your account.');
    }
}
