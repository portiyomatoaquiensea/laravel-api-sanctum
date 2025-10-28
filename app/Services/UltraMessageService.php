<?php

namespace App\Services;

class UltraMessageService
{
    private string $waIdInstance;
    private string $waApiToken;
    private string $ultraIdInstance;
    private string $ultraApiToken;
    private string $waCountryCode;

    public function __construct()
    {
        $this->waIdInstance    = "1101747700";
        $this->waApiToken      = "ef277cdb679a4c8dbdfafe495265e7d81ed9f99dc7954624a5";
        $this->ultraIdInstance = "instance10160";
        $this->ultraApiToken   = "hbamowmzk68r0k6f";
        $this->waCountryCode   = "+62";
    }

    /**
     * Check WhatsApp number using Green-API
     */
    public function checkWhatsapp(?string $phoneNumber): array
    {
        $endpoint = "https://api.green-api.com/waInstance{$this->waIdInstance}/CheckWhatsapp/{$this->waApiToken}";

        $response = [
            'error' => false,
            'message' => '',
            'is_wa' => 'E',
        ];

        $phoneNumber = $this->getPhone($phoneNumber);
        if (!$phoneNumber) {
            $response['error'] = true;
            return $response;
        }

        $payload = json_encode(['phoneNumber' => $phoneNumber]);
        $headers = ['Content-Type: application/json'];

        $output = $this->curlRequest($endpoint, 'POST', $payload, $headers);
        $data   = json_decode($output);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data->statusCode) && $data->statusCode == 400) {
                $response['error'] = true;
                $response['is_wa'] = 'E';
            }
            if (isset($data->existsWhatsapp)) {
                $response['is_wa'] = $data->existsWhatsapp ? 'Y' : 'N';
            }
        } else {
            $response['error'] = true;
            $response['is_wa'] = 'EI';
        }

        return $response;
    }

    /**
     * Check WhatsApp number using UltraMsg API
     */
    public function ultraCheckWhatsapp(?string $phoneNumber): array
    {
        $endpoint = "https://api.ultramsg.com/{$this->ultraIdInstance}/contacts/check";

        $response = [
            'error' => false,
            'message' => '',
            'is_wa' => 'E',
        ];

        $phoneNumber = $this->getPhone($phoneNumber);
        if (!$phoneNumber) {
            $response['error'] = true;
            return $response;
        }

        $query = http_build_query([
            'token'   => $this->ultraApiToken,
            'chatId'  => $phoneNumber . '@c.us',
            'nocache' => '1',
        ]);

        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        $output = $this->curlRequest($endpoint . '?' . $query, 'GET', null, $headers);
        $data   = json_decode($output);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data->error) && $data->error) {
                $response['error']   = true;
                $response['message'] = $data->error;
                $response['is_wa']   = 'E';
            }
            if (isset($data->status)) {
                $response['is_wa'] = $data->status === 'valid' ? 'Y' : 'N';
            }
        } else {
            $response['error'] = true;
            $response['is_wa'] = 'EI';
        }

        return $response;
    }

    /**
     * Helper for phone number formatting
     */
    private function getPhone(?string $phoneNumber): ?string
    {
        if (is_numeric($phoneNumber)) {
            return $this->waCountryCode . (int)$phoneNumber;
        }
        return null;
    }

    /**
     * Generic cURL request helper
     */
    private function curlRequest(string $url, string $method = 'GET', ?string $payload = null, array $headers = []): string
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($payload !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            }
        }

        $output = curl_exec($curl);
        curl_close($curl);

        return $output ?: '';
    }
}
