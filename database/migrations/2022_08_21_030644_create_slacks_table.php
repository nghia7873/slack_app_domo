<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSlacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('slacks', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->text('token')->nullable();
            $table->text('webhook_domo')->nullable();
            $table->text('webhook_slack')->nullable();
            $table->text('webhook_domo_alert')->nullable();
            $table->text('channel_bot_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('slacks');
    }
}
