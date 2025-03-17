<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use App\Models\UUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Check SuperAdmin first
        $user = SuperAdmin::where('email', $request->email)->first();
        $role = 'superadmin';

        // If not SuperAdmin, check regular User
        if (!$user) {
            $user = UUser::where('email', $request->email)->first();
            $role = 'user';
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->status) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive'
            ], 403);
        }

        $token = $user->createToken('AuthToken', [$role])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'role' => $role,
                'token' => $token,
            ]
        ]);
    }
}
