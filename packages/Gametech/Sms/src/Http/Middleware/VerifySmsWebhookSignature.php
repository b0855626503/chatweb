<?php

namespace Gametech\Sms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class VerifySmsWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        // 1) Shared token (ง่าย/เร็ว/ชัวร์) — แนะนำ
        if (! $this->verifySharedToken($request)) {
            return response()->json(['ok' => false, 'error' => 'UNAUTHORIZED_WEBHOOK_TOKEN'], 401);
        }

        // 2) Vonage signature (sig) — เปิดใช้เมื่อ Vonage เปิด signed webhooks ให้แล้ว :contentReference[oaicite:2]{index=2}
        if (config('sms.webhooks.vonage.signature.enabled', false)) {
            if (! $this->verifyVonageSig($request)) {
                return response()->json(['ok' => false, 'error' => 'INVALID_SIGNATURE'], 401);
            }
        }

        return $next($request);
    }

    private function verifySharedToken(Request $request): bool
    {
        $expected = (string) config('sms.webhooks.vonage.token', '');
        if ($expected === '') {
            // ถ้ายังไม่ตั้ง token ให้ “fail-closed” ดีกว่าเปิดรูให้ยิงเล่น
            return false;
        }

        $provided =
            (string) $request->header('X-Webhook-Token', '') ?:
                (string) $request->query('token', '') ?:
                    (string) $request->input('token', '');

        return hash_equals($expected, $provided);
    }

    private function verifyVonageSig(Request $request): bool
    {
        $secret = (string) config('sms.webhooks.vonage.signature.secret', '');
        $method = strtolower((string) config('sms.webhooks.vonage.signature.method', 'md5hash'));
        $tolerance = (int) config('sms.webhooks.vonage.signature.timestamp_tolerance', 300);

        if ($secret === '') {
            return false;
        }

        // Vonage แนะนำรวม query + body แต่โปรดระวัง: SMS API ระบุว่า POST + query พร้อมกันอาจแปลกได้
        // เรารองรับไว้เพื่อความอึดในสนาม แต่สุดท้ายควรให้ Vonage ส่งแบบเดียวสม่ำเสมอ :contentReference[oaicite:3]{index=3}
        $params = array_merge($request->query(), is_array($request->all()) ? $request->all() : []);

        $sig = (string) Arr::get($params, 'sig', '');
        if ($sig === '') {
            return false;
        }

        // ต้องใช้ timestamp ที่มากับ request (ตอน validate ให้ “ลบ sig ออก” แล้วใช้ timestamp ที่ให้มา) :contentReference[oaicite:4]{index=4}
        $timestamp = (int) Arr::get($params, 'timestamp', 0);
        if ($timestamp <= 0) {
            return false;
        }

        // กัน replay attack
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        unset($params['sig']);

        $data = $this->buildVonageSignatureBaseString($params);

        $generated = $this->generateVonageSignature($data, $secret, $method);

        return hash_equals(strtolower($sig), strtolower($generated));
    }

    /**
     * Step 1 (Vonage): sort by key, replace '&' and '=' with '_' in values,
     * then build string starting with '&' like: &akey=value&bkey=value :contentReference[oaicite:5]{index=5}
     */
    private function buildVonageSignatureBaseString(array $params): string
    {
        ksort($params);

        $parts = [];

        foreach ($params as $key => $value) {
            // Flatten scalar only (Vonage params เป็น key/value ธรรมดา)
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif ($value === null) {
                $value = '';
            } else {
                $value = (string) $value;
            }

            // Replace & and = with _ as docs say :contentReference[oaicite:6]{index=6}
            $safeValue = str_replace(['&', '='], '_', $value);

            $parts[] = $key . '=' . $safeValue;
        }

        return '&' . implode('&', $parts);
    }

    /**
     * Step 2 (Vonage):
     * - MD5 hash: append secret to end and md5 hex :contentReference[oaicite:7]{index=7}
     * - HMAC: hash_hmac with secret and hex output :contentReference[oaicite:8]{index=8}
     *
     * Methods: md5hash, md5, sha1, sha256, sha512 :contentReference[oaicite:9]{index=9}
     */
    private function generateVonageSignature(string $base, string $secret, string $method): string
    {
        // Vonage docs แยก “MD5 hash” กับ “HMAC signatures” :contentReference[oaicite:10]{index=10}
        $method = strtolower($method);

        if ($method === 'md5hash' || $method === 'md5') {
            return md5($base . $secret);
        }

        // HMAC methods
        if (in_array($method, ['sha1', 'sha256', 'sha512'], true)) {
            return hash_hmac($method, $base, $secret);
        }

        // default: conservative fail
        return '';
    }
}
