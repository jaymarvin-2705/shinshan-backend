<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\ClientProfile;
use App\DTOs\ClientData;
use App\Support\TrinidadLocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\DTOs\ChangePasswordDTO;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\ResetPasswordDTO;
use App\Exceptions\PasswordResetException;
use App\Services\PasswordService;

class AuthController extends Controller
{
    public function __construct(
        private PasswordService $passwordService
    ) {}

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role')
            ->where('email', $credentials['email'])
            ->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.',
                ],
            ]);
        }

        if ($user->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is waiting for admin approval.',
                'status' => 'pending',
            ], 403);
        }

        if ($user->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved. Please contact the administrator.',
                'status' => 'rejected',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact the administrator.',
                'status' => $user->status,
            ], 403);
        }

        $token = $user->createToken(
            'shinshan-api-token'
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'user_id' => $user->user_id,
                    'fullname' => $user->fullname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role' => $user->role?->role_name,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => [
                'user_id' => $user->user_id,
                'fullname' => $user->fullname,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role' => $user->role?->role_name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'fullname' => [
                'required_without:firstname',
                'nullable',
                'string',
                'max:255',
            ],
            'firstname' => [
                'required_without:fullname',
                'nullable',
                'string',
                'max:80',
            ],
            'middlename' => [
                'nullable',
                'string',
                'max:80',
            ],
            'lastname' => [
                'required_with:firstname',
                'nullable',
                'string',
                'max:80',
            ],
            'birth_date' => [
                'required_with:firstname',
                'nullable',
                'date_format:Y-m-d',
                'before:today',
            ],
            'barangay' => [
                'required_with:firstname',
                'nullable',
                'string',
                Rule::in(TrinidadLocationCatalog::barangays()),
            ],
            'purok' => [
                'required_with:firstname',
                'nullable',
                'string',
                Rule::in(TrinidadLocationCatalog::puroks(
                    $request->input('barangay')
                )),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ], [
            'email.unique' => 'This email is already registered. Please use another email or log in.',
        ]);

        $validated['firstname'] = isset($validated['firstname'])
            ? trim($validated['firstname'])
            : null;
        $validated['middlename'] = isset($validated['middlename'])
            ? trim($validated['middlename']) ?: null
            : null;
        $validated['lastname'] = isset($validated['lastname'])
            ? trim($validated['lastname'])
            : null;
        $validated['email'] = strtolower(trim($validated['email']));
        $fullname = $validated['firstname'] !== null
            && $validated['firstname'] !== ''
            ? implode(' ', array_filter([
                $validated['firstname'],
                $validated['middlename'],
                $validated['lastname'],
            ], static fn (?string $name): bool => $name !== null && $name !== ''))
            : trim($validated['fullname']);

        $clientData = ClientData::fromArray([
            ...$validated,
            'fullname' => $fullname,
            'phone' => isset($validated['phone'])
                ? trim($validated['phone'])
                : null,
        ]);

        $clientRole = Role::where(
            'role_name',
            'Client'
        )->first();

        if (!$clientRole) {
            return response()->json([
                'success' => false,
                'message' => 'Client role is not configured.',
            ], 500);
        }

        [$user, $profile] = DB::transaction(function () use (
            $validated,
            $clientData,
            $clientRole
        ) {
            $user = new User();

            $user->fill($clientData->toUserArray());
            $user->password = Hash::make(
                $validated['password']
            );
            $user->role_id = $clientRole->role_id;
            $user->status = 'pending';
            $user->save();

            $profile = ClientProfile::create([
                'user_id' => $user->user_id,
                ...$clientData->toProfileArray(),
            ]);
            return [$user, $profile];
        });

        return response()->json([
            'success' => true,
            'message' => 'Registration submitted successfully. Please wait for admin approval before signing in.',
            'data' => [
                'user' => [
                    'user_id' => $user->user_id,
                    'firstname' => $user->firstname,
                    'middlename' => $user->middlename,
                    'lastname' => $user->lastname,
                    'fullname' => $user->fullname,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role' => 'Client',
                ],
                'client_profile' => [
                    'client_id' => $profile->client_id,
                    'birth_date' => $profile->birth_date?->toDateString(),
                    'barangay' => $profile->barangay,
                    'purok' => $profile->purok,
                ],
            ],
        ], 201);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->passwordService->sendResetOtp(
                ForgotPasswordDTO::fromArray($data)
            );
        } catch (PasswordResetException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }

        return response()->json([
            'success' => true,
            'message' => 'If the email is registered, a 6-digit code has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        try {
            $this->passwordService->resetPassword(
                ResetPasswordDTO::fromArray($data)
            );
        } catch (PasswordResetException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->status());
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful. Please log in.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ]);

        $this->passwordService->changePassword(
            $request->user(),
            ChangePasswordDTO::fromArray($data)
        );

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}