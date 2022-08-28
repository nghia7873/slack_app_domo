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
            'token' => '',
            'webhook_domo' => '',
            'webhook_slack' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook/1',
            'webhook_domo_alert' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook_domo/1'
        ]);

        DB::table('slacks')->insert([
            'user_id' => 2,
            'token' => '',
            'webhook_domo' => '',
            'webhook_slack' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook/2',
            'webhook_domo_alert' => 'https://stage-domo-slack-conn.developmentlab.tokyo/api/webhook_domo/2'
        ]);
    }
}
