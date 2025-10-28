<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GalleryService
{
    protected string $baseUrl;

    public function __construct()
    {
        // You can set the base URL via config or .env
        // $this->baseUrl = config('services.upload_api.url', env('API_UPLOAD_URL'));
        $this->baseUrl =  "https://api-u3.suka-dev.com";
    }

    public function readUrl(string $path, string $package, string $image): ?string
    {
        return "{$this->baseUrl}/galleries/read3?path={$path}&package={$package}&image={$image}";
    }

    /**
     * Upload using saveBase64 endpoint
     */
    public function upload(array $data = []): ?object
    {
        return $this->postEndpoint($data, 'saveBase64');
    }

    /**
     * Upload using uploadBase64 endpoint
     */
    public function uploadBase64(array $data = []): ?object
    {
        return $this->postEndpoint($data, 'uploadBase64');
    }

    /**
     * Common POST request handler
     */
    private function postEndpoint(?array $data, string $method): ?object
    {
        $url = "{$this->baseUrl}/galleries/{$method}";
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		$output = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($output);
		return $data;

    }
}
