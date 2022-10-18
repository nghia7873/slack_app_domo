<?php

namespace App\Jobs;

use App\Exports\LinkedinExport;
use App\Http\Controllers\Controller;
use App\Models\Linkedin;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class LinkedinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $client;
    public $email;

    public $timeout = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email)
    {
        set_time_limit(0);
        $this->email = $email;
    }

    /**
     * Execute the job.
     *
     * @return string
     */
    public function handle()
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

            $details[] = ['First Name', 'Last Name', 'Location', 'Summary', 'Occupation', 'Experience', 'Education', 'Licenses & Certifications',
                'Languages', 'Skills', 'Connection'];

            foreach ($allUser as $user) {
                foreach ($user['profile'] as $profiles) {
                    $details[] = $this->getProfileDetail($profiles, $user['network']);
                }
            }

            $this->exportExcel($this->email, $details);
            dispatch(new SendEmail($this->email));
        } catch (\Exception $e) {
            logger($e);
            $this->clearCache();

            throw new \Exception($e);
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
        $maxResult = 400;

        $startDate = Carbon::now();  //08
        logger("start : $startDate");
        $endDate = $startDate->copy()->addHour(); //09

        while (true) {
            $isMaxResult = isset($listUsersFirst['profile']) && (count($listUsersFirst['profile']) > $maxResult);
            $timeRune = Carbon::now(); //08:10 //09:10
            $isRateLimit = $timeRune->gt($startDate) && $timeRune->lt($endDate);
            logger("Timerun : $timeRune");

            if ($isMaxResult && $isRateLimit) {
                sleep(3600);
                $startDate = Carbon::now(); //09:10
                logger("startNew : $startDate");
                $endDate = $startDate->copy()->addHour(); //10:10
                $maxResult += count($listUsersFirst['profile']); // 1200
                logger("maxResult : $maxResult");
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

    function clearCache()
    {
        Cache::forget('cookie');
        Cache::forget('csrf_token');
        Cache::forget('create_cookie');
        Cache::forget('is_true');
    }

    private function exportExcel($email, $details)
    {
        try {
            $time = strtotime("now");
            $details = new LinkedinExport($details);
            $path = "$email-$time.xlsx";

            Excel::store($details, $path);
            Linkedin::create([
                'status' => 'success',
                'link' => $path,
                'name' => $email,
            ]);
        } catch (\Exception $e) {
            throw new \Exception($e);
        }

    }
}
