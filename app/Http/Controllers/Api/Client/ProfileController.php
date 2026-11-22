<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TrinidadLocationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Client profile retrieved successfully.',
            'data' => $this->profileData($request->user()),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $clientProfile = $user->clientProfile()->first();
        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:80'],
            'middlename' => ['nullable', 'string', 'max:80'],
            'lastname' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'barangay' => [
                'nullable',
                'string',
                Rule::in(TrinidadLocationCatalog::barangays()),
            ],
            'purok' => [
                'nullable',
                'string',
                Rule::in(TrinidadLocationCatalog::puroks(
                    $request->input('barangay') ?? $clientProfile?->barangay
                )),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['firstname'] = trim($validated['firstname']);
        $validated['middlename'] = isset($validated['middlename'])
            ? trim($validated['middlename']) ?: null
            : null;
        $validated['lastname'] = trim($validated['lastname']);
        $validated['email'] = strtolower(trim($validated['email']));
        $validated['phone'] = isset($validated['phone'])
            ? trim($validated['phone']) ?: null
            : null;

        DB::transaction(function () use ($user, $clientProfile, $validated) {
            $user->fill([
                'firstname' => $validated['firstname'],
                'middlename' => $validated['middlename'],
                'lastname' => $validated['lastname'],
                'fullname' => implode(' ', array_filter([
                    $validated['firstname'],
                    $validated['middlename'],
                    $validated['lastname'],
                ])),
                'email' => $validated['email'],
                'phone' => $validated['phone'],
            ])->save();

            $clientProfile ??= $user->clientProfile()->firstOrNew([]);
            $clientProfile->fill([
                'birth_date' => $validated['birth_date'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'purok' => $validated['purok'] ?? null,
                'address' => $validated['address'] ?? null,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Client profile updated successfully.',
            'data' => $this->profileData($user->fresh()),
        ]);
    }

    public function updatePhoto(Request $request)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $clientProfile = $user->clientProfile()->firstOrNew([]);
        $newPath = $validated['photo']->storePublicly('client-profiles', 'public');

        if (!is_string($newPath) || $newPath === '') {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        $oldPath = $clientProfile->profile_photo_path;

        try {
            $clientProfile->update(['profile_photo_path' => $newPath]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully.',
            'data' => $this->profileData($user->fresh()),
        ]);
    }

    private function profileData(User $user): array
    {
        $user->loadMissing(['role', 'clientProfile']);
        $profile = $user->clientProfile;

        return [
            'user_id' => $user->user_id,
            'firstname' => $user->firstname,
            'middlename' => $user->middlename,
            'lastname' => $user->lastname,
            'fullname' => $user->fullname,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'role' => $user->role?->role_name,
            'profile_photo_url' => $profile?->profile_photo_path
                ? Storage::disk('public')->url($profile->profile_photo_path)
                : null,
            'client_profile' => $profile ? [
                'client_id' => $profile->client_id,
                'birth_date' => $profile->birth_date?->toDateString(),
                'barangay' => $profile->barangay,
                'purok' => $profile->purok,
                'address' => $profile->address,
            ] : null,
        ];
    }
}
