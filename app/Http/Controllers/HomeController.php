<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $all_users = User::all();
        return view('home')->with(['all_users' => $all_users]);
    }

    /**
     * Get new users via AJAX
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNewUsers(Request $request)
    {
        $newUsers = User::where('created_at', '>=', now()->subMinutes(60)->toDateTimeString())
                       ->orderBy('created_at', 'desc')
                       ->get(['id', 'name', 'email', 'created_at']);

        // Verify a password against this hash
        // $password = '87654321';
        // $hash = '$2b$12$1kTa8dndE.X/bs6GgSEsROVoxDkwZfSmOvKGUeIf1.GPF0q4ppQ2e';

        // Use PHP's native password_verify (supports all bcrypt variants)
        // if (password_verify($password, $hash)) {
        //     $result = "Password matches! ✅";
        // } else {
        //     $result = "Password does not match! ❌";
        // }

        return response()->json([
            'success' => true,
            'users' => $newUsers,
            'count' => $newUsers->count(),
            // 'password_check' => $result,
            // 'hash_info' => [
            //     'algorithm' => 'bcrypt',
            //     'variant' => '$2b$ (bcrypt variant)',
            //     'cost' => '12',
            //     'tested_password' => $password
            // ]
        ]);

    }
}
