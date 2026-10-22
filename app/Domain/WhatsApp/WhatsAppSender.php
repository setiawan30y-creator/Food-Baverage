<?php

namespace App\Domain\WhatsApp;

use Illuminate\Support\Facades\Http;

/**
 * Pengirim WhatsApp lewat gateway pihak ketiga (Fonnte / Wablas).
 * Konfigurasi: ['provider' => ..., 'token' => ..., 'base_url' => ..., 'secret_key' => ...]
 * @2026-Alifiandra
 */
class WhatsAppSender
{
    public function __construct(protected array $config) {}

    public function provider(): string
    {
        return (string) ($this->config['provider'] ?? '');
    }

    /** Ubah nomor lokal ke format internasional: 0812… / +62812… → 62812… */
    public static function normalize(?string $phone): ?string
    {
        $p = preg_replace('/\D/', '', (string) $phone);
        if ($p === '' || strlen($p) < 9) {
            return null;
        }
        if (str_starts_with($p, '0')) {
            $p = '62'.substr($p, 1);
        } elseif (str_starts_with($p, '8')) {
            $p = '62'.$p;
        }

        return strlen($p) <= 15 ? $p : null;
    }

    /** Kirim pesan. Mengembalikan respons gateway; melempar \RuntimeException bila gagal. */
    public function send(string $to, string $message): array
    {
        $token = (string) ($this->config['token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Token WhatsApp gateway belum diisi.');
        }

        if ($this->provider() === 'fonnte') {
            $res = Http::withHeaders(['Authorization' => $token])->asForm()->timeout(20)
                ->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62']);
            $j = $res->json() ?? [];
            if (! $res->successful() || ($j['status'] ?? false) !== true) {
                throw new \RuntimeException('Fonnte: '.($j['reason'] ?? $j['detail'] ?? 'HTTP '.$res->status()));
            }

            return $j;
        }

        if ($this->provider() === 'wablas') {
            $res = Http::withHeaders(['Authorization' => $this->wablasAuth()])->asForm()->timeout(20)
                ->post($this->wablasBase().'/api/send-message', ['phone' => $to, 'message' => $message]);
            $j = $res->json() ?? [];
            if (! $res->successful() || ($j['status'] ?? false) !== true) {
                throw new \RuntimeException('Wablas: '.($j['message'] ?? 'HTTP '.$res->status()));
            }

            return $j;
        }

        throw new \RuntimeException('Penyedia WhatsApp tidak dikenal.');
    }

    /** Cek token & status perangkat. */
    public function test(): string
    {
        $token = (string) ($this->config['token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Token belum diisi.');
        }
        if ($this->provider() === 'fonnte') {
            $res = Http::withHeaders(['Authorization' => $token])->asForm()->timeout(20)->post('https://api.fonnte.com/device');
            $j = $res->json() ?? [];
            if (($j['status'] ?? false) !== true) {
                throw new \RuntimeException('Fonnte menolak token: '.($j['reason'] ?? 'HTTP '.$res->status()));
            }
            $state = $j['device_status'] ?? 'tidak diketahui';
            if ($state !== 'connect') {
                throw new \RuntimeException('Token valid, tetapi perangkat WhatsApp berstatus "'.$state.'". Hubungkan (scan QR) di dashboard Fonnte.');
            }

            return 'Fonnte terhubung · perangkat '.($j['device'] ?? '').' aktif.';
        }
        if ($this->provider() === 'wablas') {
            if (! filter_var($this->wablasBase(), FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Alamat server Wablas belum benar.');
            }
            $res = Http::withHeaders(['Authorization' => $this->wablasAuth()])->timeout(20)->get($this->wablasBase().'/api/device/info', ['token' => $token]);
            $j = $res->json() ?? [];
            if (! $res->successful() || ($j['status'] ?? false) !== true) {
                throw new \RuntimeException('Wablas menolak token: '.($j['message'] ?? 'HTTP '.$res->status()));
            }

            return 'Wablas terhubung.';
        }

        throw new \RuntimeException('Penyedia WhatsApp tidak dikenal.');
    }

    protected function wablasBase(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    protected function wablasAuth(): string
    {
        $secret = (string) ($this->config['secret_key'] ?? '');

        return $this->config['token'].($secret !== '' ? '.'.$secret : '');
    }
}
