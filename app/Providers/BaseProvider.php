<?php

namespace App\Providers;

use App\Models\OauthBase;
use App\Models\OauthEccube;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;

class BaseProvider extends AbstractProvider
{
    const CHUNK_ROW = 200;

    public function getEccubeUrl()
    {
        return 'https://api.thebase.in';
    }

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getEccubeUrl() . '/1/oauth/authorize', $state);
    }

    public function getAccessTokenByRefreshToken($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => $this->getTokenHeaders($code),
            RequestOptions::FORM_PARAMS => $this->getTokenFieldsRefresh($code),
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function getTokenFieldsRefresh($code)
    {
        return [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    public function getGraphqlCustomer($link)
    {
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'access_token');
        $refreshToken = Arr::get($response, 'refresh_token');
        $oauth2 = OauthBase::latest()->first();

        if ($oauth2) {
            $oauth2->update([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        } else {
            OauthBase::create([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        }

        $data = [];
        $page = 1;
        $customer = $this->getCustomerByToken($token, $page);

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $array = collect($data)->chunk(self::CHUNK_ROW)->toArray();

        foreach ($array as $item) {
             $client->post($link,
                [
                    'body' => json_encode($item)
                ]
            );
        }

        return true;
    }

    public function getGraphqlOrder($link)
    {
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'access_token');
        $refreshToken = Arr::get($response, 'refresh_token');
        $oauth2 = OauthBase::latest()->first();

        if ($oauth2) {
            $oauth2->update([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        } else {
            OauthBase::create([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        }

        $offset = 0;

        $data = [];
        $customer = $this->getOrderByToken($token, $offset);

        if (empty($customer['orders'])) {
            return true;
        }

        $handle = $this->handleDataOrder($customer['orders'], $token);

        $data = array_merge($data, $handle);

        while (!empty($customer['orders'])) {
            $offset += 100;
            $customer = $this->getOrderByToken($token, $offset);
            $handle = $this->handleDataOrder($customer['orders'], $token);
            $data = array_merge($data, $handle);
        }

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $array = collect($data)->chunk(150)->toArray();

        foreach ($array as $item) {
            $response = $client->post($link,
                [
                    'body' => json_encode(array_values($item))
                ]
            );
        }

        return true;
    }

    private function handleDataProduct($products)
    {
        $data = [];
        foreach ($products as $product) {
            $data[] = [
              'id' => $product['item_id'],
              'name' => $product['title'],
              'description_detail' => $product['detail'],
              'price' => $product['price'],
              'stock' => $product['stock'],
            ];
        }

        return $data;
    }

    private function handleDataOrder($orders, $token)
    {
        $data = [];
        foreach ($orders as $order) {
            $array = [];
            $orderDetails = $this->getOrderByIdUnique($token, $order['unique_key']);
            $array['total'] = $order['total'];
            $array['full_name'] = $order['first_name'] . " " . $order['last_name'];
            $array['delivery_date'] = $order['delivery_date'];
            $array['payment'] = $order['payment'];
            $array['status'] = $order['dispatch_status'];
            $array['shipping_method'] = $orderDetails['shipping_method'];
            $array['discount'] = $orderDetails['order_discount']['discount'];
            $quantity = 0;
            foreach ($orderDetails['order_items'] as $orderItems) {
                $quantity += $orderItems['amount'];
                $array['title'] = $orderItems['title'];
            }
            $array['quantity'] = $quantity;

            $data[] = $array;
        }

        return $data;
    }

    public function getGraphqlProduct($link)
    {
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'access_token');
        $refreshToken = Arr::get($response, 'refresh_token');
        $oauth2 = OauthBase::latest()->first();

        if ($oauth2) {
            $oauth2->update([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        } else {
            OauthBase::create([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        }

        $offset = 0;

        $data = [];
        $customer = $this->getProductByToken($token, $offset);

        if (empty($customer['items'])) {
            return true;
        }

        $handle = $this->handleDataProduct($customer['items']);

        $data = array_merge($data, $handle);

        while (!empty($customer['items'])) {
            $offset += 100;
            $customer = $this->getProductByToken($token, $offset);
            $handle = $this->handleDataProduct($customer['items']);
            $data = array_merge($data, $handle);
        }

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $array = collect($data)->chunk(50)->toArray();

        foreach ($array as $item) {
            $response = $client->post($link,
                [
                    'body' => json_encode(array_values($item))
                ]
            );
        }

        return true;
    }

    protected function getCustomerByToken($token, $page)
    {
        $client = new Client();
        $response = $client->get($this->getEccubeUrl() . '/1/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getOrderByToken($token, $offset)
    {
        $query = [
            'offset' => $offset,
            'limit' => 100
        ];

        $client = new Client();
        $response = $client->get($this->getEccubeUrl() . '/1/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'query' => $query
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getOrderByIdUnique($token, $id)
    {
        $client = new Client();
        $response = $client->get($this->getEccubeUrl() . "/1/orders/detail/$id", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getProductByToken($token, $offset)
    {
        $query = [
          'offset' => $offset,
          'limit' => 100
        ];

        $client = new Client();
        $response = $client->get($this->getEccubeUrl() . '/1/items', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'query' => $query
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getTokenUrl()
    {
        return $this->getEccubeUrl() . '/1/oauth/token';
    }

    protected function mapDataToObject(array $user)
    {
        dd($user);
    }

    protected function getUserByToken($token)
    {

    }

    protected function mapUserToObject(array $user)
    {

    }
}
