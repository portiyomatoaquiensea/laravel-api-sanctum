<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CurlService
{
    private $ciphering;
    private $encryption_iv;
    private $encryption_key;
    private $options;
    public function __construct()
    {
        $this->ciphering = 'AES-128-CTR';
        $this->encryption_iv = '1234567891011121';
        $this->encryption_key = 'l8d4re62fy26c7613cc6g8b36416f389b7boe13855aafc8fdd7db78418f89e5k';
        $this->options = 0;
    }

    public function post($endpoint = null, $data = null)
	{
		$encode_data = json_encode($data);
		$header = [
			'Content-Type: application/json'
		];
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $encode_data);
		$output = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($output);
		try {
            // Your API call code here
            Log::channel('daily_api')->info('POST API URL', ['url' => $endpoint]);
            Log::channel('daily_api')->info('POST API BODY', ['encode_data' => $encode_data]);
            Log::channel('daily_api')->info('POST API HEADER', ['headers' => json_encode($header)]);
            Log::channel('daily_api')->info('POST API RESPONSE',['response' => $response]);
        } catch (\Exception $e) {
            Log::channel('daily_api')->info('POST API call failed', ['error' => $e->getMessage()]);
        }
		if (!$response) {
			return $response;
		}
		return $response;
	}

	public function get($endpoint = null, $data = null)
	{
		$header = [
			'Content-Type: application/json'
		];
		$encode_data = json_encode($data);
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $endpoint,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_POSTFIELDS => $encode_data,
			CURLOPT_HTTPHEADER => $header,
		]);
		$output = curl_exec($curl);
		curl_close($curl);

		$response = json_decode($output);
		if (!$response) {
			return [];
		}
        try {
            // Your API call code here
            Log::channel('daily_api')->info('GET API URL', ['url' => $endpoint]);
            Log::channel('daily_api')->info('GET API BODY', ['encode_data' => $encode_data]);
            Log::channel('daily_api')->info('GET API HEADER', ['headers' => json_encode($header)]);
            Log::channel('daily_api')->info('GET API RESPONSE',['response' => $response]);
        } catch (\Exception $e) {
            Log::channel('daily_api')->info('GET API call failed', ['error' => $e->getMessage()]);
        }

		return $response;
	}

    public function encrypt($text = null)
    {
        if ($text === null) {
            return null;
        }

        $data = openssl_encrypt($text, $this->ciphering, $this->encryption_key, $this->options, $this->encryption_iv);
        return $data;
    }

    public function decrypt($encrypt = null)
    {
        if ($encrypt === null) {
            return null;
        }

        $data = openssl_decrypt($encrypt, $this->ciphering, $this->encryption_key, $this->options, $this->encryption_iv);
        return $data;
    }

	public function postX88($endpoint = null, $data = null)
	{
		
		$signature = hash_hmac(
			'sha512',
			config('credential.X88_INTERNAL_KEY').json_encode($data),
			config('credential.X88_INTERNAL_TOKEN'));
		$encode_data = json_encode($data);
		$header = [
			'Content-Type: application/json',
			'On-Key: ' . config('credential.X88_INTERNAL_KEY'),
			'On-Token: ' . config('credential.X88_INTERNAL_TOKEN'),
			'On-Signature: ' . $signature
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		$output = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($output);
		if (!$response) {
			return $response;
		}
		
        try {
            // Your API call code here
            Log::channel('daily_api')->info('X88 API URL', ['url' => $endpoint]);
            Log::channel('daily_api')->info('X88 API BODY', ['encode_data' => $encode_data]);
            Log::channel('daily_api')->info('X88 API HEADER', ['headers' => json_encode($header)]);
            Log::channel('daily_api')->info('X88 API RESPONSE',['response' => $response]);
        } catch (\Exception $e) {
            Log::channel('daily_api')->info('X88 API call failed', ['error' => $e->getMessage()]);
        }
		return $response;
	}


	public function postFiji($endpoint = null, $data = [])
	{
		$header = [
			'Content-Type: application/json',
			'internal-key: q9uYhT0XpC5dGj7sFkVwYnO2LcPmKtRnDsQaHfJkLlMtNvPqRsUwVxYzAbDcFgHeIfJgKlNmOpQrStUvWwC'
		];
		$apiUrl = 'https://sk-fiji.sukabet.co/api/'.$endpoint;
		
		$encode_data = json_encode($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $encode_data);
		$output = curl_exec($ch);
		curl_close($ch);
		try {
            // Your API call code here
            Log::channel('daily_api')->info('FIJI API URL', ['url' => $apiUrl]);
            Log::channel('daily_api')->info('FIJI API BODY', ['encode_data' => $encode_data]);
            Log::channel('daily_api')->info('FIJI API HEADER', ['headers' => json_encode($header)]);
            Log::channel('daily_api')->info('FIJI API RESPONSE',['response' => $response]);
        } catch (\Exception $e) {
            Log::channel('daily_api')->info('FIJI API call failed', ['error' => $e->getMessage()]);
        }
		return json_decode($output);
	}
}