<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\OtpChannel;
use App\Domain\Auth\Models\OtpVerification;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const COOLDOWN_SECONDS = 60;

    /**
     * Generate and send OTP.
     *
     * @return array{otp_id: string, expires_at: string}
     */
    public function sendOtp(
        User $user,
        string $purpose = 'login',
        OtpChannel $channel = OtpChannel::Sms,
    ): array {
        $identifier = match ($channel) {
            OtpChannel::Email => $user->email,
            OtpChannel::Sms, OtpChannel::Whatsapp => $user->phone,
        };

        if (! $identifier) {
            throw ValidationException::withMessages([
                'channel' => [__("No {$channel->value} contact available for this user.")],
            ]);
        }

        // Check cooldown
        $recent = OtpVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(self::COOLDOWN_SECONDS))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'otp' => [__('Please wait before requesting a new OTP.')],
            ]);
        }

        // Invalidate old OTPs for same purpose
        OtpVerification::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->delete();

        // Generate OTP
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        $otpRecord = OtpVerification::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'identifier' => $identifier,
            'otp_hash' => Hash::make($otp),
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ]);

        // Dispatch OTP via channel (log in dev, SMS/email in prod)
        $this->dispatchOtp($channel, $identifier, $otp);

        return [
            'otp_id' => $otpRecord->id,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Verify OTP.
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $otpId, string $otp): OtpVerification
    {
        $record = OtpVerification::find($otpId);

        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => [__('Invalid OTP request.')],
            ]);
        }

        if ($record->isVerified()) {
            throw ValidationException::withMessages([
                'otp' => [__('OTP has already been used.')],
            ]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages([
                'otp' => [__('OTP has expired. Please request a new one.')],
            ]);
        }

        if ($record->hasExceededAttempts(self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'otp' => [__('Too many failed attempts. Please request a new OTP.')],
            ]);
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->incrementAttempts();

            throw ValidationException::withMessages([
                'otp' => [__('Invalid OTP code.')],
            ]);
        }

        $record->markVerified();

        return $record;
    }

    /**
     * Generate a numeric OTP code.
     */
    private function generateOtp(): string
    {
        return str_pad(
            (string) random_int(0, (int) (10 ** self::OTP_LENGTH) - 1),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    /**
     * Dispatch OTP to the user via the specified channel.
     */
    private function dispatchOtp(OtpChannel $channel, string $identifier, string $otp): void
    {
        // In development, log the OTP
        if (app()->environment('local', 'testing')) {
            Log::info("OTP for {$identifier}: {$otp}");
            return;
        }

        // TODO: Integrate real SMS/Email providers
        match ($channel) {
            OtpChannel::Sms => $this->sendSms($identifier, $otp),
            OtpChannel::Email => $this->sendEmail($identifier, $otp),
            OtpChannel::Whatsapp => $this->sendWhatsapp($identifier, $otp),
        };
    }

    private function sendSms(string $phone, string $otp): void
    {
        // TODO: Implement SMS gateway integration (Twilio, MessageBird, etc.)
        Log::info("SMS OTP to {$phone}: {$otp}");
    }

    private function sendEmail(string $email, string $otp): void
    {
        // TODO: Implement email sending via Laravel Mail
        Log::info("Email OTP to {$email}: {$otp}");
    }

    private function sendWhatsapp(string $phone, string $otp): void
    {
        // TODO: Implement WhatsApp Business API
        Log::info("WhatsApp OTP to {$phone}: {$otp}");
    }
}
