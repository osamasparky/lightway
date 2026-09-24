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
        Schema::create('user_accesses', function (Blueprint $table) {
            $table->id();

            $table->integer('sale_id')->unsigned()->nullable();

            $table->integer('buyer_id')->unsigned()->nullable();

            $table->integer('user_id')->unsigned();

            $table->integer('subscribe_id')->unsigned()->nullable();

            $table->integer('accessible_id')->unsigned();

            $table->string('accessible_type');

            $table->string('source_type')->nullable();

            $table->integer('created_at')->unsigned()->nullable();

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();

            $table->foreign('buyer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index([
                'accessible_type',
                'accessible_id'
            ]);

            $table->index('subscribe_id');
            $table->index('sale_id');

            $table->unique([
                'user_id',
                'accessible_type',
                'accessible_id'
            ], 'user_access_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_accesses');
    }
};