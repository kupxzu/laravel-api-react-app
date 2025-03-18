<?php

namespace App\Http\Controllers;

use App\Models\UUser;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

class PasswordResetController extends Controller
{
    /**
     * Send password reset code (forgot password)
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'user_type' => 'required|in:user,superadmin',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $userExists = false;
            $userType = $request->user_type;

            // Check if user exists based on user type
            if ($userType === 'user') {
                $userExists = UUser::where('email', $email)->exists();
            } else {
                $userExists = SuperAdmin::where('email', $email)->exists();
            }

            if (!$userExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No account found with this email address'
                ], 404);
            }

            // Delete any existing tokens for this email
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            // Create verification code - 6 digit number
            $verificationCode = mt_rand(100000, 999999);
            $expiresAt = Carbon::now()->addMinutes(15);

            // Store code in database
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => $verificationCode,
                'user_type' => $userType,
                'created_at' => Carbon::now(),
                'expires_at' => $expiresAt
            ]);

            // Send email with verification code
            Mail::send('emails.verification_code', ['verificationCode' => $verificationCode], function($message) use ($email) {
                $message->to($email);
                $message->subject('Your Password Reset Verification Code');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Verification code has been sent to your email'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify reset code and reset password
     */
    public function verifyCodeAndReset(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'verification_code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $email = $request->email;
            $verificationCode = $request->verification_code;

            // Check if verification code is valid
            $tokenData = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('token', $verificationCode)
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$tokenData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired verification code'
                ], 400);
            }

            // Generate new random password (12 characters)
            $newPassword = Str::random(12);
            
            // Update user password based on user type
            if ($tokenData->user_type === 'user') {
                $user = UUser::where('email', $email)->first();
                if (!$user) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User not found'
                    ], 404);
                }
                $user->password = Hash::make($newPassword);
                $user->save();
            } else {
                $superAdmin = SuperAdmin::where('email', $email)->first();
                if (!$superAdmin) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'SuperAdmin not found'
                    ], 404);
                }
                $superAdmin->password = Hash::make($newPassword);
                $superAdmin->save();
            }

            // Delete the verification code
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();
                
            // Send email with new password
            Mail::send('emails.password_changed', ['newPassword' => $newPassword], function($message) use ($email) {
                $message->to($email);
                $message->subject('Your Password Has Been Reset');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset successfully',
                'data' => [
                    'new_password' => $newPassword
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}