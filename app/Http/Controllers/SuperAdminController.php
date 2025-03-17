<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{
    /**
     * SuperAdmin login
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check email
            $superAdmin = SuperAdmin::where('email', $request->email)->first();
            
            if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check status
            if (!$superAdmin->status) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your account is inactive. Please contact support.'
                ], 403);
            }

            // Create token
            $token = $superAdmin->createToken('SuperAdminToken')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'super_admin' => $superAdmin,
                    'token' => $token
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SuperAdmin logout
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SuperAdmin profile
     */
    public function profile(Request $request)
    {
        try {
            $superAdmin = $request->user();
            
            return response()->json([
                'status' => 'success',
                'data' => $superAdmin
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SuperAdmin profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:super_admins,email,' . $request->user()->id,
                'phone' => 'sometimes|nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $superAdmin = $request->user();
            
            if ($request->has('name')) {
                $superAdmin->name = $request->name;
            }
            
            if ($request->has('email')) {
                $superAdmin->email = $request->email;
            }
            
            if ($request->has('phone')) {
                $superAdmin->phone = $request->phone;
            }
            
            $superAdmin->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => $superAdmin
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $superAdmin = $request->user();
            
            // Check current password
            if (!Hash::check($request->current_password, $superAdmin->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ], 400);
            }
            
            // Update password
            $superAdmin->password = Hash::make($request->new_password);
            $superAdmin->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new SuperAdmin account (only existing SuperAdmin can do this)
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:super_admins,email',
                'password' => 'required|string|min:8',
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $superAdmin = SuperAdmin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'status' => true,
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'SuperAdmin created successfully',
                'data' => $superAdmin
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create SuperAdmin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all SuperAdmins (only for SuperAdmin)
     */
    public function index()
    {
        try {
            $superAdmins = SuperAdmin::select('id', 'name', 'email', 'phone', 'status', 'created_at', 'updated_at')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $superAdmins
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch SuperAdmins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SuperAdmin status (activate/deactivate)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            // Prevent self-deactivation
            if ($request->user()->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot change your own status'
                ], 400);
            }
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $superAdmin = SuperAdmin::findOrFail($id);
            $superAdmin->status = $request->status;
            $superAdmin->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'SuperAdmin status updated successfully',
                'data' => $superAdmin
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update SuperAdmin status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}