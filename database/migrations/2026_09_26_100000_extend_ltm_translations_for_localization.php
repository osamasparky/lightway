<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Localization manager: review state on the existing translation-manager table.
 * Additive only — the package keeps reading/writing the same columns.
 */
return new class extends Migration {
    public function up()
    {
        Schema::table('ltm_translations', function (Blueprint $table) {
            // null = derived from the value (empty -> missing, filled -> translated)
            $table->string('review_status', 20)->nullable()->after('value');
            // null = imported from the language files
            $table->string('source', 10)->nullable()->after('review_status');
            $table->unsignedBigInteger('translation_job_id')->nullable()->after('source');
            $table->unsignedInteger('reviewed_by')->nullable()->after('translation_job_id');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        // `key` is TEXT, so joins/lookups go through a stored hash of group + key.
        DB::statement("ALTER TABLE `ltm_translations` ADD COLUMN `key_hash` CHAR(40) GENERATED ALWAYS AS (SHA1(CONCAT(`group`, '|', `key`))) STORED AFTER `key`");

        Schema::table('ltm_translations', function (Blueprint $table) {
            $table->index(['key_hash', 'locale'], 'ltm_key_hash_locale_index');
            $table->index(['locale', 'group'], 'ltm_locale_group_index');
        });
    }

    public function down()
    {
        Schema::table('ltm_translations', function (Blueprint $table) {
            $table->dropIndex('ltm_key_hash_locale_index');
            $table->dropIndex('ltm_locale_group_index');
            $table->dropColumn(['key_hash', 'review_status', 'source', 'translation_job_id', 'reviewed_by', 'reviewed_at']);
        });
    }
};
