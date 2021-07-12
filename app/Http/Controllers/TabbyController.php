<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class TabbyController extends Controller
{
    public $public;
    public $secret;
    public $merchant;

    public function __construct()
    {
        if ( env('TABBY_TEST') ) {
            $this->public = env('TABBY_TEST_PUBLIC');
            $this->secret = env('TABBY_TEST_SECRET');
        } else {
            $this->public = env('TABBY_PUBLIC');
            $this->secret = env('TABBY_SECRET');
        }

        $this->merchant = env('TABBY_MERCHANT');
    }

    // $payment = [
    //     'amount' => 'string',
    //     'description' => 'string',
    //     'currency' => 'SAR',
    //     'buyer' => ['name' => 'string', 'email' => ''string, 'phone' => 'string'],
    //     'order' => ['reference_id' => ''string]
    // ];
    // $urls = ['success' => 'string', 'cancel' => 'string', 'failure' => 'string'];
    public function createSession(array $payment, array $urls)
    {
        $uri = 'https://api.tabby.ai/api/v2/checkout';
        $client = new Client();
        try {
            $response = $client->post($uri, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->public
                ],
                'json' => [
                    'lang' => LaravelLocalization::getCurrentLocale(),
                    'merchant_code' => $this->merchant,
                    'payment' => $payment,
                    'merchant_urls' => $urls
                ]
            ]);
        } catch (RequestException $e) {
            Log::error($e);
            return;
        }
        
        return json_decode($response->getBody()->getContents(), true);
    }

    public function payment($id)
    {
        $uri = 'https://api.tabby.ai/api/v1/payments';
        $client = new Client();
        try {
            $response = $client->get($uri . '/' . $id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secret
                ]
            ]);
        } catch (RequestException $e) {
            Log::error($e);
            return;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    // Webhook 
    public function webhook(Request $request)
    {
        // well no need for webhook in this application...
    }
}
