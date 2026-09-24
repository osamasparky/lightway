<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('temp_users', function (Blueprint $table) {
            $table->id();

            $table->integer('buyer_id')->unsigned();
            $table->integer('order_id')->unsigned()->nullable();
            $table->integer('order_item_id')->unsigned()->nullable();

            // users info
            $table->string('full_name');
            $table->string('email');
            $table->string('password');

            $table->integer('created_at')->unsigned();
            $table->integer('updated_at')->unsigned();

            $table->foreign('buyer_id')->on('users')->references('id')->cascadeOnDelete();
            $table->foreign('order_id')->on('orders')->references('id')->cascadeOnDelete();
            $table->foreign('order_item_id')->on('order_items')->references('id')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_users');
    }
};
