<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()) {
            return redirect('/');
        }
        $first = ! User::exists();
        $token = config('auth.buksza_setup_token');
        if ($first && app()->environment('production') && is_string($token) && $token !== '' && is_string($request->query('setup')) && hash_equals($token, $request->query('setup'))) {
            $request->session()->put('setup_authorized', true);
            $request->session()->regenerate();

            return redirect('/belepes');
        }
        $allowed = ! app()->environment('production') || $request->session()->get('setup_authorized') === true;

        return Inertia::render('Auth', ['setup' => $first && $allowed, 'setupLocked' => $first && ! $allowed]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data, true)) {
            throw ValidationException::withMessages(['email' => 'Az e-mail-cím vagy a jelszó nem megfelelő.']);
        }
        $request->session()->regenerate();

        return redirect('/');
    }

    public function setup(Request $request)
    {
        abort_if(User::exists(), 403);
        abort_unless(! app()->environment('production') || $request->session()->get('setup_authorized') === true, 403);
        $data = $request->validate(['name' => 'required|string|max:80', 'email' => 'required|email|max:254', 'password' => 'required|string|min:12|confirmed']);
        $user = DB::transaction(function () use ($data) {
            abort_if(User::exists(), 403);

            return User::create(['id' => 1, 'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        });
        Auth::login($user, true);
        $request->session()->forget('setup_authorized');
        $request->session()->regenerate();

        return redirect('/')->with('message', 'A saját kasszád elkészült. Add meg a kezdő összegeket a Beállításokban.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/belepes');
    }
}
