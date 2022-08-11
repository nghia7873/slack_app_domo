<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Vluzrmos\SlackApi\Facades\SlackChat;
use GuzzleHttp\Client;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function handleWebhookDomo(Request $request)
    {
        $payload = $request->all();
        SlackChat::message('D03SM7MM3V5', $payload['message']);
    }

    public function handleWebhook(Request $request)
    {
        logger(123213);
        $message = json_decode($request->get('event')['text'], true) ?? '';

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $client->post('https://test-dev-426230.domo.com/api/iot/v1/webhook/data/eyJhbGciOiJIUzI1NiJ9.eyJzdHJlYW0iOiIyOTg2Y2ExOWUyZjQ0NDJmYTk1OTQwYjlkMjdhZmU5MTptbW1tLTAwMjMtMjA4MjoxMzk3MzI0NDQyIn0.Y3nPiKJ2q0jJLByvXg3b3GsfZvqrA1vq0YZKOAz5KYI',
            ['body' => json_encode($message)]
        );

        return response()->json(['challenge' => $request->get('challenge')], 200);
    }

    public function handleWebhookDomoOauth(Request $request)
    {
        logger(131231232);
    }
}
