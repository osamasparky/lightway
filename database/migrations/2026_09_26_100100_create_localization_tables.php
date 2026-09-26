<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // AI provider settings (the API key is stored encrypted by TranslationSettings).
        Schema::create('translation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('translation_glossary', function (Blueprint $table) {
            $table->id();
            $table->string('term', 255);
            // null translation = keep the term as-is (brand / product names)
            $table->string('translation', 255)->nullable();
            // null locale = applies to every target language
            $table->string('locale', 20)->nullable()->index();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('translation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('source_locale', 20);
            $table->string('target_locale', 20);
            $table->string('scope', 20); // missing | all | retranslate
            $table->json('groups')->nullable();
            $table->string('status', 20)->index(); // pending | running | paused | cancelled | completed | failed
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->unsignedSmallInteger('batch_size');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('batches')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->boolean('publish_on_finish')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('translation_job_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('translation_job_id');
            $table->string('group', 255);
            $table->text('key');
            $table->char('key_hash', 40);
            $table->string('status', 20)->default('pending'); // pending | done | failed | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['translation_job_id', 'status'], 'tji_job_status_index');
            $table->unique(['translation_job_id', 'key_hash'], 'tji_job_key_unique');
            $table->foreign('translation_job_id')->references('id')->on('translation_jobs')->cascadeOnDelete();
        });

        // Where each key is referenced in code (filled by `php artisan localization:scan-usage`).
        Schema::create('translation_key_usages', function (Blueprint $table) {
            $table->id();
            $table->char('key_hash', 40)->index();
            $table->string('file', 255);
            $table->unsignedInteger('line');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('translation_key_usages');
        Schema::dropIfExists('translation_job_items');
        Schema::dropIfExists('translation_jobs');
        Schema::dropIfExists('translation_glossary');
        Schema::dropIfExists('translation_settings');
    }
};
