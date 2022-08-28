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

    public function handleWebhookDomo($id, Request $request)
    {
        if (empty($id)) {
            return response()->json(['text' => 'ok'], 200);
        }

        $payload = $request->all();
        $tokenSlack = Slack::where('id', $id)->first();
        $slack = app('Vluzrmos\SlackApi\Contracts\SlackApi');
        $slack->setToken($tokenSlack->token);
        $slack->load('Chat')->message($tokenSlack->channel_bot_id, $payload['message']);

        return response()->json(['text' => 'ok'], 200);
    }

    public function handleWebhook($id, Request $request)
    {
        if (empty($id) || isset($request->get('event')['bot_id'])) {
            return response()->json(['text' => 'ok'], 200);
        }

        if ($request->get('challenge')) {
            return response()->json(['challenge' => $request->get('challenge')], 200);
        }

        $eventText = $request->get('event')['text'];
        $jsonValidate = $this->jsonValidate($eventText);

        $channelId = $request->get('event')['channel'] ?? '';

        Slack::where('id', $id)->update([
            'channel_bot_id' => $channelId
        ]);

        if ($jsonValidate['message'] == $eventText) {
            return response()->json(['text' => 'ok'], 200);
        }
        $firstSlack = Slack::where('id', $id)->first();

        if ($jsonValidate['error']) {
            $slack = app('Vluzrmos\SlackApi\Contracts\SlackApi');
            $slack->setToken($firstSlack->token);
            $slack->load('Chat')->message($firstSlack->channel_bot_id, $jsonValidate['message']);

            return response()->json(['text' => 'ok'], 200);
        }

        $message = json_decode($request->get('event')['text'], true) ?? '';

        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        $client->post($firstSlack->webhook_domo,
            ['body' => json_encode($message)]
        );

        return response()->json(['text' => 'ok'], 200);
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

    public function postEditAccountSlack(Request $request)
    {
        Slack::where('id', $request->id)->update($request->except('_token'));

        return redirect()->route('list-slack');
    }

    public function createAccountSlack()
    {
        return view('create-slack');
    }

    public function postCreateAccountSlack(Request $request)
    {
        $data = $request->except('_token');
        $slack = Slack::create($data);
        $url = url('/');

        Slack::where('id', $slack->id)->update([
            'webhook_slack' => "$url/api/webhook/" . $slack->id,
            'webhook_domo_alert' => "$url/api/webhook_domo/" . $slack->id
        ]);

        return redirect()->route('list-slack');
    }

    private function jsonValidate($string)
    {
        // decode the JSON data
        $result = json_decode($string);

        // switch and check possible JSON errors
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $error = ''; // JSON is valid // No error has occurred
                break;
            case JSON_ERROR_DEPTH:
                $error = 'The maximum stack depth has been exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Invalid or malformed JSON.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Control character error, possibly incorrectly encoded.';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON.';
                break;
            // PHP >= 5.3.3
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_RECURSION:
                $error = 'One or more recursive references in the value to be encoded.';
                break;
            // PHP >= 5.5.0
            case JSON_ERROR_INF_OR_NAN:
                $error = 'One or more NAN or INF values in the value to be encoded.';
                break;
            case JSON_ERROR_UNSUPPORTED_TYPE:
                $error = 'A value of a type that cannot be encoded was given.';
                break;
            default:
                $error = 'Unknown JSON error occured.';
                break;
        }

        if ($error !== '') {
            // throw the Exception or exit // or whatever :)
            return [
              'error' => true,
              'message' => $error
            ];
        }

        // everything is OK
        return [
            'error' => false,
            'message' => ''
        ];
    }
}
