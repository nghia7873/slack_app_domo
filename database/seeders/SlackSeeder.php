<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('slacks')->insert([
           'user_id' => 1,
            'token' => 'xoxp-3919767600658-3922224697780-3933082586977-80fb3870953ec84d85a346f63ecb0a23',
            'webhook_domo' => 'https://test-dev-426230.domo.com/api/iot/v1/webhook/data/eyJhbGciOiJIUzI1NiJ9.eyJzdHJlYW0iOiIyOTg2Y2ExOWUyZjQ0NDJmYTk1OTQwYjlkMjdhZmU5MTptbW1tLTAwMjMtMjA4MjoxMzk3MzI0NDQyIn0.Y3nPiKJ2q0jJLByvXg3b3GsfZvqrA1vq0YZKOAz5KYI',
            'webhook_slack' => '',
            'webhook_domo_alert' => ''
        ]);

        DB::table('slacks')->insert([
            'user_id' => 2,
            'token' => '',
            'webhook_domo' => '',
            'webhook_slack' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook',
            'webhook_domo_alert' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook_domo'
        ]);
    }
}
