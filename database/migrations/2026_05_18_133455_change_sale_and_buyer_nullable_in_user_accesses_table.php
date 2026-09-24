<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_accesses', function ($table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['buyer_id']);
        });

        DB::statement("
            ALTER TABLE user_accesses
            MODIFY sale_id INT UNSIGNED NOT NULL
        ");

        DB::statement("
            ALTER TABLE user_accesses
            MODIFY buyer_id INT UNSIGNED NOT NULL
        ");

        Schema::table('user_accesses', function ($table) {

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign('buyer_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('user_accesses', function ($table) {

            $table->dropForeign(['sale_id']);
            $table->dropForeign(['buyer_id']);
        });

        DB::statement("
            ALTER TABLE user_accesses
            MODIFY sale_id INT UNSIGNED NULL
        ");

        DB::statement("
            ALTER TABLE user_accesses
            MODIFY buyer_id INT UNSIGNED NULL
        ");

        Schema::table('user_accesses', function ($table) {

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();

            $table->foreign('buyer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};