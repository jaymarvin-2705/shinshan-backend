<?php

namespace App\Services;

use App\DTOs\ChangePasswordDTO;
use App\DTOs\ForgotPasswordDTO;
use App\DTOs\ResetPasswordDTO;
use App\Exceptions\PasswordResetException;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Repositories\PasswordResetOtpRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private UserRepository $users,
        private PasswordResetOtpRepository $otps,
    ) {}

    public function sendResetOtp(ForgotPasswordDTO $dto): void
    {
        $user = $this->users->findByEmail($dto->email);

        // Silently do nothing for unknown/inactive accounts (prevents email enumeration)
        if (!$user || $user->status !== 'active') {
            return;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->otps->replaceForEmail(
            $user->email,
            Hash::make($otp),
            now()->addMinutes(self::OTP_TTL_MINUTES)
        );

        try {
            Mail::to($user->email)->send(new PasswordResetOtpMail($user->fullname, $otp));
        } catch (\Throwable $e) {
            Log::error('Reset OTP mail failed: ' . $e->getMessage());
            $this->otps->deleteByEmail($user->email);

            throw new PasswordResetException('Unable to send the email. Please try again later.', 500);
        }
    }

    public function resetPassword(ResetPasswordDTO $dto): void
    {
        $record = $this->otps->findByEmail($dto->email);

        if (!$record || now()->greaterThan($record->expires_at)) {
            throw new PasswordResetException('The code is invalid or has expired.');
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $this->otps->deleteByEmail($dto->email);
            throw new PasswordResetException('Too many attempts. Please request a new code.', 429);
        }

        if (!Hash::check($dto->otp, $record->otp)) {
            $this->otps->incrementAttempts($record);
            throw new PasswordResetException('The code is incorrect.');
        }

        $user = $this->users->findByEmail($dto->email);

        if (!$user) {
            throw new PasswordResetException('The code is invalid or has expired.');
        }

        $this->users->updatePassword($user, Hash::make($dto->password));

        $user->tokens()->delete();            // revoke all Sanctum sessions
        $this->otps->deleteByEmail($dto->email);
    }

    public function changePassword(User $user, ChangePasswordDTO $dto): void
    {
        if (!Hash::check($dto->currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $this->users->updatePassword($user, Hash::make($dto->newPassword));
    }
}