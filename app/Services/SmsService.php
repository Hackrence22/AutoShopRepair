<?php

namespace App\Services;

class SmsService
{
    /**
     * Send an SMS message using Twilio if credentials are configured.
     * Falls back to no-op if env vars are missing.
     */
    public function send(string $toPhoneE164, string $message): bool
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        $from = env('TWILIO_FROM');

        if (!$sid || !$token || !$from) {
            // Not configured; skip sending
            return false;
        }

        try {
            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create($toPhoneE164, [
                'from' => $from,
                'body' => $message,
            ]);
            return true;
        } catch (\Throwable $e) {
            // Log and continue without failing the request
            \Log::warning('SMS send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Best-effort phone normalization to E.164. Assumes PH by default (+63) if starts with 0.
     */
    public function toE164(?string $raw, string $defaultCountry = '+63'): ?string
    {
        if (!$raw) { return null; }
        $digits = preg_replace('/[^0-9+]/', '', $raw);
        if (!$digits) { return null; }
        if (strpos($digits, '+') === 0) { return $digits; }
        if (strpos($digits, '0') === 0) { return $defaultCountry . substr($digits, 1); }
        return $defaultCountry . $digits;
    }
}


