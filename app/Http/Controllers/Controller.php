<?php

namespace App\Http\Controllers;

use App\Exports\LinkedinExport;
use App\Jobs\LinkedinJob;
use App\Jobs\SendEmail;
use App\Models\Base;
use App\Models\Eccube;
use App\Models\Linkedin;
use App\Models\Slack;
use Carbon\Carbon;
use Dflydev\DotAccessData\Data;
use Doctrine\DBAL\Exception;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    const AUTH_HEADERS = [
        'X-Li-User-Agent' => 'LIAuthLibrary:3.2.4 com.linkedin.LinkedIn:8.8.1 iPhone:8.3',
        'User-Agent' => 'LinkedIn/8.8.1 CFNetwork/711.3.18 Darwin/14.0.0',
        'X-User-Language' => 'en',
        'X-User-Locale' => 'en_US',
        'Accept-Language' => 'en-us',
        'Content-Type' => 'application/x-www-form-urlencoded'
    ];

    const REQUEST_AUTH_HEADERS = [
        "user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36",
        "accept-language" => "en-AU,en-GB;q=0.9,en-US;q=0.8,en;q=0.7",
        "x-li-lang" => "en_US",
        "x-restli-protocol-version" => "2.0.0",
    ];

    protected $client;

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

    function verifyAccount($cookie, $ajax, $sessionKey, $sessionPassword)
    {
        $a = "curl --location --request POST 'https://www.linkedin.com/uas/authenticate' \
--header 'Content-Type: application/x-www-form-urlencoded' \
--header 'X-Li-User-Agent: LIAuthLibrary:3.2.4 com.linkedin.LinkedIn:8.8.1 iPhone:8.3' \
--header 'User-Agent: LinkedIn/8.8.1 CFNetwork/711.3.18 Darwin/14.0.0' \
--header 'X-User-Language: en' \
--header 'X-User-Locale: en_US' \
--header 'Accept-Language: en-us' \
--header 'Cookie: $cookie' \
--data-urlencode 'session_key=$sessionKey' \
--data-urlencode 'session_password=$sessionPassword' \
--data-urlencode 'JSESSIONID=$ajax'
";

        return str_replace("\n", "", $a);
    }

    function authenLinkedin()
    {
        $client = new Client();
        $res = $client->get('https://www.linkedin.com/uas/authenticate');
        $response = json_decode($res->getBody()->getContents());

        if ($response->status !== 'success') {
            return '';
        }
        $cookie = $res->getHeader('Set-Cookie')[1];
        $split = explode(";", $cookie)[0];
        $split1 = explode("=", $split)[1];
        $cookie = str_replace('"', '', $split1);
        $this->cache('csrf_token', $cookie);
        $this->cache('create_cookie', implode(";",$res->getHeader('Set-Cookie')));
    }

    function cache($key, $value)
    {
        $expiresAt = Carbon::now()->addMonths(2);
        Cache::put($key, $value, $expiresAt);
    }

    function handleLinkedin(Request $request)
    {
        try {
            $this->authenLinkedin();

            $payload = [
                'session_key' => $request->get('session_key'),
                'session_password' => $request->get('session_password'),
                'JSESSIONID' => Cache::get('csrf_token')
            ];

            $headers = self::AUTH_HEADERS;
            $headers['cookie'] = Cache::get('create_cookie');

            $client = new Client([
                'headers' => $headers
            ]);

            $res = $client->post('https://www.linkedin.com/uas/authenticate',
                ['form_params' => $payload
                ]);

            $this->cache('cookies', $res->getHeader('Set-Cookie'));
//            $data = $this->me();

            //jobs
            dispatch(new LinkedinJob($request->get('session_key')));

            return response()->json([
                'data' => '<br>
                    <p>Login successful & background downloading now.</p>
                    <p>Will be advised to LinkedIn email address after download complete once available.</p>',
                'status' => 200
            ], 200);
        } catch (\Exception $e) {
            $link = $this->verifyAccount(Cache::get('create_cookie'), Cache::get('csrf_token'), $request->get('session_key'),
                $request->get('session_password'));
            $message = $e->getMessage();
            preg_match('/{(?:[^{}]*|(?R))*}/', $message, $output_array);

            if (!isset($output_array[0])) {
                return response()->json(['data' => null, 'status' => 400, 'message' => $link], 200);
            }

            return response()->json(['data' => null, 'status' => 401], 200);
        }
    }

    function me()
    {
        try {
            $load = [
                'cookie' => implode(";", Cache::get('cookies')),
                'csrf-token' => Cache::get('csrf_token')
            ];

            $this->client = new Client([
                'headers' => $load
            ]);

            $res = $this->client->get('https://www.linkedin.com/voyager/api/me');

            $me = json_decode($res->getBody()->getContents());

            $publicIdMe = str_replace("urn:li:fs_miniProfile:", "", $me->miniProfile->entityUrn);
//            $usersNetworkF = $this->getProfileNetworkInfo($publicIdMe);
            $usersNetworkS = [];
//
//            if (empty($usersNetworkF)) {
//                return [];
//            }

//            foreach ($usersNetworkF['profile'] as $userNetworkF) {
//                $usersNetworkS[] = $this->getProfileNetworkInfo($userNetworkF, 'S', true);
//            }

            $allUser = array_filter(array_merge([$this->getProfileNetworkInfo($publicIdMe, 'F', true)],
                $usersNetworkS));

            if (empty($allUser)) {
                return [];
            }

            $details = [];

            foreach ($allUser as $user) {
                foreach ($user['profile'] as $profiles) {
                    $details[] = $this->getProfileDetail($profiles, $user['network']);
                }
            }

            return $details;
        } catch (\Exception $e) {
            logger($e);
            $this->clearCache();
        }
    }

    public function getProfileNetworkInfo($publicId, $network = 'F', $isProfile = false)
    {
        $count = 49;
        $filters = "List(resultType->PEOPLE,connectionOf->$publicId,network->$network)";
        $origin = "GLOBAL_SEARCH_HEADER";
        $q = 'all';
        $start = 0;
        $listUsersFirst = [];
        $maxResult = 300;

        while (true) {
            if (isset($listUsersFirst['profile']) && count($listUsersFirst['profile']) > $maxResult) {
                break;
            }

            $queryContext = "List(spellCorrectionEnabled->true,relatedSearchesEnabled->true,kcardTypes->PROFILE|COMPANY)";
            $hehe = $this->client->get("https://www.linkedin.com/voyager/api/search/blended?count=$count&filters=$filters&origin=$origin&q=$q&start=$start&queryContext=$queryContext");
            $data1 = json_decode($hehe->getBody()->getContents());

            if (empty($data1->elements)) {
               break;
            }

            //get public id each network First
            $listUsersFirst['network'] = "Default connection";

            foreach ($data1->elements as $element) {
                foreach ($element->elements as $user) {
                    if ($isProfile) {
                        $listUsersFirst['profile'][] = $user->publicIdentifier;

                        if ($network === 'S' && isset( $user->socialProofImagePile[0]->attributes[0])) {
                            $listUsersFirst['network'] =
                                $user->socialProofImagePile[0]->attributes[0]->miniProfile->firstName . " " .
                                $user->socialProofImagePile[0]->attributes[0]->miniProfile->lastName;
                        }
                    } else {
                        $listUsersFirst['profile'][] = str_replace("urn:li:fs_miniProfile:", "", $user->targetUrn);
                    }
                }
            }

            $start += $count;
        }

        return $listUsersFirst;
    }

    public function getProfileDetail($publicId, $peopleShare = '')
    {
        $res = $this->client->get("https://www.linkedin.com/voyager/api/identity/profiles/$publicId/profileView");
        $data1 = json_decode($res->getBody()->getContents());
        $geoLocation = $data1->profile->geoLocationName ?? '';
        $locationName = $data1->profile->locationName ?? '';
        $summary =  $data1->profile->summary ?? '';
        $occupation = $data1->profile->miniProfile->occupation ?? '';

        if ($peopleShare !== 'Default connection') {
            $peopleShare = $peopleShare . " is a shared connection";
        }

        $firstName =  $data1->profile->firstName ?? '';
        $lastName =  $data1->profile->lastName ?? '';

        return [
            $firstName,
            $lastName,
            $geoLocation . " " . $locationName,
            $summary,
            $occupation,
            implode("\n", $this->getExperience($data1)),
            implode("\n", $this->getEducation($data1)),
            implode("\n", $this->getLicensesAndCerifications($data1)),
            implode("\n", $this->getLanguages($data1)),
            implode("\n", $this->getSkills($data1)),
            $peopleShare
        ];
    }

    public function getExperience($data)
    {
        $experience = [];
        foreach ($data->positionView->elements as $element) {
            $month = $element->timePeriod->startDate->month ?? '';
            $startDate = $element->timePeriod->startDate->year ?? '';
            $endDate = $element->timePeriod->endDate->year ?? '';
            if (isset($element->timePeriod)) {
                if (empty($element->timePeriod->endDate)) {
                    if (!empty($month)) {
                        $string = $startDate . "-" .$month;
                        $timePeriod = $string . " - " . "Present";
                    } else {
                        $timePeriod = $startDate . " - " . "Present";
                    }

                } else {
                    $timePeriod = $startDate . "-" . $endDate;
                }
            } else {
                $timePeriod = '';
            }

            $title = $element->title ?? '';
            $companyName = $element->companyName ?? '';
            $geoLocationName = $element->geoLocationName ?? '';

            $experience[] = $title . " " . $companyName. " " . $geoLocationName . " " .
                $timePeriod;
        }

        return $experience;
    }

    public function getEducation($data)
    {
        $education = [];
        foreach ($data->educationView->elements as $element) {
            if (isset($element->timePeriod->startDate)) {
                $endDate = $element->timePeriod->endDate->year ?? '';
                $startDate = $element->timePeriod->startDate->year ?? '';
                $timePeriod = $startDate . "-" .$endDate;
            }

            $timePeriod = $timePeriod ?? '';
            $schoolName = $element->school->schoolName ?? '';
            $fieldOfStudy = $element->fieldOfStudy ?? '';
            $description = $element->description ?? '';

            $education[] = $schoolName . " " . $fieldOfStudy. " " . $timePeriod . " ". $description;
        }

        return $education;
    }

    public function getLicensesAndCerifications($data)
    {
        $licenses = [];
        foreach ($data->certificationView->elements as $element) {
            if (isset($element->timePeriod->startDate)) {
                $year = $element->timePeriod->startDate->year ?? '';
                $timePeriod = $year;
            }

            $name = $element->name ?? '';
            $authority = $element->authority ?? '';
            $timePeriod = $timePeriod ?? '';

            $licenses[] = $name . " " .$authority . " " . $timePeriod;
        }

        return $licenses;
    }

    public function getLanguages($data)
    {
        $languages = [];
        foreach ($data->languageView->elements as $element) {
            $languages[] = $element->name ?? '';
        }

        return $languages;
    }

    public function getSkills($data)
    {
        $skills = [];
        foreach ($data->skillView->elements as $element) {
            $skills[] = $element->name ?? '';
        }

        return $skills;
    }

    function linkedCookie()
    {
        return view('linked-cookie');
    }

    public function getLinkedinJob()
    {
        $linkedin = Linkedin::select('name', 'link')->get();

        return response()->json(['data' => $linkedin], 200);
    }

    function clearCache()
    {
        Cache::forget('cookie');
        Cache::forget('csrf_token');
        Cache::forget('create_cookie');
        Cache::forget('is_true');
    }

    public function downloadLinked($file)
    {
        $path = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        return response()->download("$path/$file");
    }

    public function cube()
    {
        $url = url('');
        $urlFree = [
            'https://domo-eccube-v1.developmentlab.tokyo',
            'http://domo-eccube-v1.developmentlab.tokyo',
        ];
        $urlPaid = [
            'https://domo-eccube-v2.developmentlab.tokyo',
            'http://domo-eccube-v2.developmentlab.tokyo',
        ];


        if (in_array($url, $urlFree)) {
            $title = 'Free Domo - EC-CUBE Dashboard';
        } elseif (in_array($url, $urlPaid)) {
            $title = 'Paid Domo - EC-CUBE Dashboard ';
        } else {
            $title = 'Laravel';
        }

        return view('eccube', compact('title'));
    }

    public function eccube()
    {
        return Socialite::driver('ec-cube')->scopes(['read'])->redirect();
    }

    public function eccubeRedirect(Request $request)
    {
        try {
            $driver = Socialite::driver('ec-cube');
            $type = session('type');
            $data = Eccube::where('type', $type)->firstOrFail();

            switch ($data->type) {
                case 'customer':
                    $driver->getGraphqlCustomer($data->webhook);
                    break;
                case 'order':
                    $driver->getGraphqlOrder($data->webhook);
                    break;
                case 'product':
                    $driver->getGraphqlProduct($data->webhook);
                    break;
                default:
                    break;
            }

            return redirect()->route('cube')->with('message', 'データの同期成功');
        } catch (\Exception $e) {
            logger("ECCUBE : $e");
            return redirect()->route('cube')->with('error', 'サーバーエラー');
        }
    }

    public function handleCube(Request $request)
    {
        Eccube::updateOrCreate(
            [
                'type' => $request->get('type')
            ],
            [
                'webhook' => $request->get('webhook'),
            ],
        );
        session(['type' => $request->get('type')]);

        return redirect()->route('ec-cube');
    }

    public function hook(Request $request)
    {
        try {
            foreach ($request->all() as $data) {
                $entity = $data['entity'];
                $action = $data['action'];
                if ($action != 'created') {
                    break;
                }
                $id = $data['id'];
                $driver = Socialite::driver('ec-cube');

                $webhook = Eccube::where('type', $entity)->firstOrFail();
                switch ($entity) {
                    case 'customer':
                        $driver->getGraphqlCustomerHook($webhook->webhook, $id);
                        break;
                    case 'order':
                        $driver->getGraphqlOrderHook($webhook->webhook, $id);
                        break;
                    case 'product':
                        $driver->getGraphqlProductHook($webhook->webhook, $id);
                        break;
                    default:
                        break;
                }

            }
        } catch (\Exception $e) {
            logger("hook : $e");
            return redirect()->route('cube')->with('error', 'サーバーエラー');
        }
    }

    public function base()
    {
        return view('base');
    }

    public function handleBase(Request $request)
    {
        Base::updateOrCreate(
            [
                'type' => $request->get('type')
            ],
            [
                'webhook' => $request->get('webhook'),
            ],
        );
        session(['type' => $request->get('type')]);

        return redirect()->route('base-oauth2');
    }

    public function baseOauth2()
    {
        $scopes = [
          'read_users read_items read_orders'
        ];

        return Socialite::driver('base')->scopes($scopes)->redirect();
    }

    public function baseRedirect(Request $request)
    {
        try {
            $driver = Socialite::driver('base');
            $type = session('type');
            $data = Base::where('type', $type)->firstOrFail();

            switch ($data->type) {
//                case 'customer':
//                    $driver->getGraphqlCustomer($data->webhook);
//                    break;
                case 'order':
                    $driver->getGraphqlOrder($data->webhook);
                    break;
                case 'product':
                    $driver->getGraphqlProduct($data->webhook);
                    break;
                default:
                    break;
            }

            return redirect()->route('base')->with('message', 'データの同期成功');
        } catch (\Exception $e) {
            logger("BASE : $e");
            return redirect()->route('base')->with('error', 'サーバーエラー');
        }
    }
}
