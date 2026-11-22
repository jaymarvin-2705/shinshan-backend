<?php

namespace App\Repositories;

use App\Models\PasswordResetOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PasswordResetOtpRepository
{
    public function replaceForEmail(string $email, string $hashedOtp, Carbon $expiresAt): PasswordResetOtp
    {
        return DB::transaction(function () use ($email, $hashedOtp, $expiresAt) {
            PasswordResetOtp::where('email', $email)->delete();

            return PasswordResetOtp::create([
                'email'      => $email,
                'otp'        => $hashedOtp,
                'expires_at' => $expiresAt,
                'attempts'   => 0,
            ]);
        });
    }

    public function findByEmail(string $email): ?PasswordResetOtp
    {
        return PasswordResetOtp::where('email', $email)->latest('id')->first();
    }

    public function incrementAttempts(PasswordResetOtp $record): void
    {
        $record->increment('attempts');
    }

    public function deleteByEmail(string $email): void
    {
        PasswordResetOtp::where('email', $email)->delete();
    }
}