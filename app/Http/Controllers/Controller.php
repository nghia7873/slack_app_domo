<?php

namespace App\Http\Controllers;

use App\Models\Slack;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Vluzrmos\SlackApi\Facades\SlackChat;
use GuzzleHttp\Client;
use Vluzrmos\SlackApi\SlackApi;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function handleWebhookDomo(Request $request)
    {
        $payload = $request->all();
        $tokenSlack = Slack::where('id', 2)->first();
        $slack = app('Vluzrmos\SlackApi\Contracts\SlackApi');
        $slack->setToken($tokenSlack->token);
        $slack->load('Chat')->message($tokenSlack->channel_bot_id, $payload['message']);
    }

    public function handleWebhook(Request $request)
    {
        $urlDomoWebhook = Slack::where('id', 2)->first();
        $message = json_decode($request->get('event')['text'], true) ?? '';
        $channelId = $request->get('event')['channel'] ?? '';

        Slack::where('id', 2)->update([
           'channel_bot_id' => $channelId
        ]);

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $client->post($urlDomoWebhook->webhook_domo,
            ['body' => json_encode($message)]
        );

        return response()->json(['challenge' => $request->get('challenge')], 200);
    }

    public function showListAccountSlack()
    {
        $slacks = Slack::all();

        return view('list-slack', compact('slacks'));
    }

    public function editAccountSlack($id)
    {
        $slack = Slack::where('id', $id)->first();

        return view('edit-slack', compact('slack'));
    }

    public function postAccountSlack(Request $request)
    {
        Slack::where('id', $request->id)->update($request->except('_token'));

        return redirect()->route('list-slack');
    }
}
