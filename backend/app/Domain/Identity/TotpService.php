<?php

namespace App\Domain\Identity;

class TotpService
{
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $secret, string $email): string
    {
        return 'otpauth://totp/'.rawurlencode('FinCore County:'.$email).'?secret='.$secret.'&issuer='.rawurlencode('FinCore County').'&algorithm=SHA1&digits=6&period=30';
    }

    private function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $message = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($input) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) === 5) {
                $encoded .= $alphabet[bindec($chunk)];
            }
        }

        return $encoded;
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(rtrim($input, '='))) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                throw new \InvalidArgumentException('Invalid MFA secret.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
