<?php

namespace App\Providers;

use App\Models\OauthEccube;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;

class EccubeProvider extends AbstractProvider
{
    const CHUNK_ROW = 200;

    public function getEccubeUrl()
    {
        return 'https://demo-eccube.development.salon';
    }

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getEccubeUrl() . '/gtmadmin/authorize', $state);
    }

    public function getGraphqlCustomer($link)
    {
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'access_token');

        $data = [];
        $page = 1;
        $customer = $this->getCustomerByToken($token, $page);

        $data = array_merge($data,  $customer['data']['customers']['nodes']);
        while($customer['data']['customers']['pageInfo']['hasNextPage']) {
            $page++;
            $customer = $this->getCustomerByToken($token, $page);
            $data = array_merge($data,  $customer['data']['customers']['nodes']);
        }

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

        $data = [];
        $page = 1;
        $customer = $this->getOrderByToken($token, $page);
        $handle = $this->handleDataOrder($customer['data']['orders']['nodes']);

        $data = array_merge($data,  $handle);

        while($customer['data']['orders']['pageInfo']['hasNextPage']) {
            $page++;
            $customer = $this->getOrderByToken($token, $page);
            $handle = $this->handleDataOrder($customer['data']['orders']['nodes']);
            $data = array_merge($data,  $handle);
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
            $priceAll = [];
            $categoryAll = [];
            $productClasses = $product['ProductClasses'];
            $productCategories = $product['ProductCategories'];
            foreach ($productClasses as $price) {
                if (isset($price['price02'])) {
                    $priceAll[] = [
                        'price' => $price['price02'],
                        'stock' => $price['stock']
                    ];
                } else {
                    $priceAll[] = [
                        'price' => $price['price01'],
                        'stock' => $price['stock']
                    ];
                }
            }
            foreach ($productCategories as $category) {
                $categoryAll[] = $category['Category']['name'] ?? '';
            }

            $maxPrice = collect($priceAll)->max('price');
            $maxStock = collect($priceAll)->sum('stock');

            $categoryMax = implode(", ",$categoryAll);
            $data[] = [
              'id' => $product['id'],
              'name' => $product['name'],
              'description_detail' => $product['description_detail'],
              'price' => $maxPrice,
              'category' => $categoryMax,
              'stock' => $maxStock,
            ];
        }

        return $data;
    }

    private function handleDataOrder($orders)
    {
        $data = [];
        foreach ($orders as $order) {
            $ordersItems = collect($order['OrderItems']);
            $filtered  = $ordersItems->filter(function ($value) {
               return $value['class_name1'] != null;
            });
            $quantityMax = $filtered->sum('quantity');
            $productName = $filtered->first()['product_name'];
            unset($order['OrderItems']);

            $order['quantity'] = $quantityMax;
            $order['product_name'] = $productName;

            $data[] = $order;
        }

        return $data;
    }

    public function getGraphqlProduct($link)
    {
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'access_token');
        $refreshToken = Arr::get($response, 'refresh_token');
        $data = OauthEccube::latest()->first();
        if ($data) {
            $data->update([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        } else {
            OauthEccube::create([
                'access_token' => $token,
                'refresh_token' => $refreshToken
            ]);
        }

        $data = [];
        $page = 1;
        $customer = $this->getProductByToken($token, $page);
        $handle = $this->handleDataProduct($customer['data']['products']['nodes']);

        $data = array_merge($data, $handle);

        while($customer['data']['products']['pageInfo']['hasNextPage']) {
            $page++;
            $customer = $this->getProductByToken($token, $page);
            $handle = $this->handleDataProduct($customer['data']['products']['nodes']);
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

    protected function getDataByToken($token)
    {
        $query = <<<'GRAPHQL'
            query {
              products {
                  edges {
                    node {
                        id
                        name
                        description_detail
                        create_date
                        ProductClasses {
                            price01
                            price02
                            stock
                        }
                        ProductCategories {
                            Category {
                                name
                            }
                        }
                    }
                }
              }
              orders {
                nodes {
                  id
                  payment_method
                  payment_date
                  payment_total
                    name01
                    name02
                    kana01
                    kana02
                    order_date
                    create_date
                    phone_number
                    addr01
                    addr02
                    postal_code
                     OrderStatus {
                        name
                    }
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
              }
               customers (page: 1) {
                nodes {
                  id
                  name01
                  name02
                  email
                  addr01
                  addr02
                  phone_number
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
              }
            }
        GRAPHQL;

        $response = $this->getHttpClient()->post($this->getEccubeUrl() . '/api', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'query' => $query
            ])
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getCustomerByToken($token, $page)
    {
        $query = <<<"GRAPHQL"
            query {
               customers (page: $page) {
                nodes {
                  id
                  name01
                  name02
                  email
                  addr01
                  addr02
                  phone_number
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
              }
            }
        GRAPHQL;

        $response = $this->getHttpClient()->post($this->getEccubeUrl() . '/api', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'query' => $query
            ])
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getOrderByToken($token, $page)
    {
        $query = <<<"GRAPHQL"
            query {
              orders (page: $page) {
                nodes {
                  id
                  payment_method
                  payment_date
                  payment_total
                    name01
                    name02
                    kana01
                    kana02
                    order_date
                    create_date
                    phone_number
                    addr01
                    addr02
                    postal_code
                    OrderItems {
                      quantity
                      product_name
                      class_name1
                    }
                    discount
                }
                totalCount
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
              }
            }
        GRAPHQL;

        $response = $this->getHttpClient()->post($this->getEccubeUrl() . '/api', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'query' => $query
            ])
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getProductByToken($token, $page)
    {
        $query = <<<"GRAPHQL"
            query {
              products (page: $page) {
                   nodes {
                        id
                        name
                        description_detail
                        create_date
                        ProductClasses {
                            price01
                            price02
                            stock
                        }
                        ProductCategories {
                            Category {
                                name
                            }
                        }
                    }
                   totalCount
                   pageInfo {
                     hasNextPage
                     hasPreviousPage
                   }
                }
           }
        GRAPHQL;

        $response = $this->getHttpClient()->post($this->getEccubeUrl() . '/api', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'query' => $query
            ])
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function getTokenUrl()
    {
        return 'https://demo-eccube.development.salon/token';
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
