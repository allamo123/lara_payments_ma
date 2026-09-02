<?php

namespace Ma\Payment\Services;

use Illuminate\Support\Facades\Http;

class ClientApiService
{
    public function post(string $endpoint, array $body)
    {
        $res = Http::withOptions([
            'verify' => false
        ])
        ->withHeaders(['content-type' => 'application/json'])
        ->post($endpoint, $body)
        ->json();

        // dd($res);

        return $res;
    }

    public function postWithSecretKey( string $endpoint, array $body, string $secretKey): array 
    {
        return Http::withOptions([
                'verify' => false,
            ])
        ->withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Token ' . $secretKey,
        ])
        ->post($endpoint, $body)
        ->throw()
        ->json();
    }

    public function get(string $endpoint, string $token)
    {
       $res = Http::withOptions(['verify' => false])
        ->withHeaders(['content-type' => 'application/json'])
        ->withToken($token)
        ->get($endpoint)
        ->json();

      return $res;
    }
}