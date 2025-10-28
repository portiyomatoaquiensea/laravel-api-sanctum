<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;

class BoPushService
{
    private string $apiUrl = 'https://bo-push.suka-dev.com/';
    private string $key    = '35zolYfFeexrPg3uXk6L97PVGSIhIB';

    /**
     * Push a deposit
     */
    public function deposit(object $data)
    {
        return $this->sendRequest('api/deposit/new', $data);
    }

    /**
     * Push a withdrawal
     */
    public function withdraw(object $data)
    {
        return $this->sendRequest('api/withdraw/new', $data);
    }

    /**
     * Push KYC data
     */
    public function kyc(object $data)
    {
        return $this->sendRequest('api/kyc', $data);
    }

    /**
     * Add new member bank
     */
    public function newMemberBank(object $data)
    {
        return $this->sendRequest('api/memberBank/new', $data);
    }

    /**
     * Push new claim bonus
     */
    public function newClaimBonus(object $data)
    {
        return $this->sendRequest('api/claimBonus/new', $data);
    }

    /**
     * Internal cURL request
     */
    private function sendRequest(string $endpoint, object $data)
    {
        $url = $this->apiUrl . $endpoint;

        // Add internal key
        $data->internal_key = $this->key;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            // Optional: log the error
            \Log::error("BoPushService cURL Error: {$err}", ['endpoint' => $endpoint, 'data' => $data]);
            return '';
        }

        return $response;
    }
}
