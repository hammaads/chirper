<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Logout extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        Auth::logout(); 

        $request->session()->invalidate();  // Not safe to reuse the session after logout
        $request->session()->regenerateToken(); // To prevent session fixation attacks (attacker uses the session to impersonate the user)

        return redirect('/')->with('success', 'Logged out');
    }
}
