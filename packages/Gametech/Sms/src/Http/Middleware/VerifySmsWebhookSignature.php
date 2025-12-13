<?php

namespace Gametech\Sms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class VerifySmsWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $provider = $this->resolveProvider($request);

        // ถ้า provider ไม่ได้เปิด signature → ผ่าน
        if (! $this->signatureEnabled($provider)) {
            return $next($request);
        }

        // verify signature ตาม provider
        if (! $this->verifySignature($request, $provider)) {
            return response()->json([
                'ok' => false,
                'error' => 'INVALID_SIGNATURE',
            ], 401);
        }

        return $next($request);
    }

    private function resolveProvider(Request $request): string
    {
        $provider = (string) $request->route('provider');

        return $provider !== ''
            ? strtolower(trim($provider))
            : strtolower(config('sms.default', 'vonage'));
    }

    private function signatureEnabled(string $provider): bool
    {
        return (bool) config(
            "sms.providers.$provider.webhooks.dlr.signature.enabled",
            false
        );
    }

    private function verifySignature(Request $request, string $provider): bool
    {
        switch ($provider) {
            case 'vonage':
                return $this->verifyVonageSignature($request, $provider);

            // provider อื่นในอนาคต
            default:
                return false;
        }
    }

    /**
     * Vonage SMS API signature verification
     */
    private function verifyVonageSignature(Request $request, string $provider): bool
    {
        $secret = (string) config("sms.providers.$provider.webhooks.dlr.signature.secret", '');
        if ($secret === '') {
            return false;
        }

        $method = strtolower(
            config("sms.providers.$provider.webhooks.dlr.signature.method", 'md5hash')
        );

        $tolerance = (int) config(
            "sms.providers.$provider.webhooks.dlr.signature.timestamp_tolerance",
            300
        );

        // Vonage ส่ง query string เป็นหลัก
        $params = $request->query();

        $sig = (string) Arr::get($params, 'sig', '');
        $timestamp = (int) Arr::get($params, 'timestamp', 0);

        if ($sig === '' || $timestamp <= 0) {
            return false;
        }

        // replay protection
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        unset($params['sig']);

        $base = $this->buildVonageBaseString($params);
        $generated = $this->generateVonageSignature($base, $secret, $method);

        return hash_equals(strtolower($sig), strtolower($generated));
    }

    private function buildVonageBaseString(array $params): string
    {
        ksort($params);

        $parts = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($value === null) {
                $value = '';
            } else {
                $value = (string) $value;
            }

            $value = str_replace(['&', '='], '_', $value);

            $parts[] = $key . '=' . $value;
        }

        return '&' . implode('&', $parts);
    }

    private function generateVonageSignature(string $base, string $secret, string $method): string
    {
        if ($method === 'md5hash' || $method === 'md5') {
            return md5($base . $secret);
        }

        if (in_array($method, ['sha1', 'sha256', 'sha512'], true)) {
            return hash_hmac($method, $base, $secret);
        }

        return '';
    }
}
