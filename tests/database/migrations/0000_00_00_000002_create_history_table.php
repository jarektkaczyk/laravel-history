<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('model_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('model');
            $table->string('action');
            $table->json('data');
            $table->bigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
            // Optionally you may want to put a FK constraint on the user_id column:
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('no action')->onUpdate('no action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('model_history');
    }
}
