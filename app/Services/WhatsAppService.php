<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $apiUrl = 'https://crm.sukabet.co/api/v1/ultramsg/verify-code';
    protected string $apiKey = '$2y$10$qZSQuYiiOpsuatLOrY5kie7p8Os5RRPd0L8kDnfv.MRkWNHQhEK6S';

    /**
     * Send OTP via WhatsApp API
     *
     * @param array $data
     * @return object|null
     */
    public function sendOtp(array $data): ?object
    {
        $data['api_key'] = $this->apiKey;

        $response = Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiUrl, $data);

        if ($response->failed()) {
            // Optionally handle or log the error here
            return null;
        }

        return json_decode($response->body());
    }
}
