<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Localization quality pipeline (additive only; the translation-manager package keeps
 * reading and writing its own columns):
 *  - translation versioning: the hash of the source text a translation was made from (outdated detection)
 *  - QA result per translation, and an AI suggestion kept aside instead of overwriting approved text
 *  - typed glossary rules, per-language translation profiles
 *  - richer job accounting (quality mode, QA tokens, memory hits, prices at the time of the job)
 */
return new class extends Migration {
    public function up()
    {
        Schema::table('ltm_translations', function (Blueprint $table) {
            $table->char('source_hash', 40)->nullable()->after('reviewed_at');
            $table->string('qa_status', 20)->nullable()->after('source_hash');
            $table->text('qa_issues')->nullable()->after('qa_status');
            $table->text('pending_value')->nullable()->after('qa_issues');
            $table->unsignedBigInteger('pending_job_id')->nullable()->after('pending_value');
        });

        // Baseline: every existing translation belongs to the current source text.
        $source = $this->sourceLocale();
        DB::statement(
            "UPDATE ltm_translations t JOIN ltm_translations s ON s.key_hash = t.key_hash AND s.locale = ?
             SET t.source_hash = SHA1(s.value)
             WHERE t.locale <> ? AND t.value IS NOT NULL AND t.value <> '' AND s.value IS NOT NULL",
            [$source, $source]
        );

        Schema::table('translation_glossary', function (Blueprint $table) {
            // preferred | do_not_translate | forbidden | brand | technical | context
            $table->string('type', 30)->default('preferred')->after('locale');
            $table->text('rule')->nullable()->after('type');
            $table->string('context', 255)->nullable()->after('rule');
            $table->boolean('active')->default(true)->after('note');
        });
        DB::table('translation_glossary')
            ->where(fn($q) => $q->whereNull('translation')->orWhere('translation', ''))
            ->update(['type' => 'do_not_translate']);

        Schema::create('translation_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('target_locale', 20)->unique();
            $table->string('name', 150);
            $table->string('locale_code', 20)->nullable();   // e.g. ar-SA
            $table->string('region', 100)->nullable();        // e.g. Saudi Arabia
            $table->string('tone', 100)->nullable();
            $table->string('audience', 300)->nullable();
            $table->text('product_context')->nullable();
            $table->text('rules')->nullable();
            $table->text('cultural_notes')->nullable();
            $table->string('quality_mode', 20)->nullable();   // economy | professional | premium
            $table->string('translation_model', 100)->nullable();
            $table->string('qa_model', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('translation_jobs', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable()->unique()->after('id');
            $table->unsignedBigInteger('profile_id')->nullable()->after('target_locale');
            $table->string('quality_mode', 20)->default('economy')->after('model');
            $table->string('qa_model', 100)->nullable()->after('quality_mode');
            $table->unsignedInteger('needs_review')->default(0)->after('failed');
            $table->unsignedInteger('memory_hits')->default(0)->after('needs_review');
            $table->unsignedInteger('api_requests')->default(0)->after('batches');
            $table->unsignedInteger('qa_prompt_tokens')->default(0)->after('completion_tokens');
            $table->unsignedInteger('qa_completion_tokens')->default(0)->after('qa_prompt_tokens');
            $table->decimal('price_input', 10, 4)->nullable()->after('qa_completion_tokens');
            $table->decimal('price_output', 10, 4)->nullable()->after('price_input');
            $table->decimal('qa_price_input', 10, 4)->nullable()->after('price_output');
            $table->decimal('qa_price_output', 10, 4)->nullable()->after('qa_price_input');
            $table->decimal('estimated_cost', 10, 4)->nullable()->after('qa_price_output');
        });
        foreach (DB::table('translation_jobs')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('translation_jobs')->where('id', $id)->update(['uuid' => (string)Str::uuid()]);
        }

        Schema::table('translation_job_items', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable()->after('status'); // ai | memory | suggestion | kept
            $table->string('qa_status', 20)->nullable()->after('outcome');
            $table->text('qa_issues')->nullable()->after('qa_status');
        });
    }

    public function down()
    {
        Schema::table('translation_job_items', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'qa_status', 'qa_issues']);
        });

        Schema::table('translation_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn([
                'uuid', 'profile_id', 'quality_mode', 'qa_model', 'needs_review', 'memory_hits', 'api_requests',
                'qa_prompt_tokens', 'qa_completion_tokens', 'price_input', 'price_output', 'qa_price_input', 'qa_price_output', 'estimated_cost',
            ]);
        });

        Schema::dropIfExists('translation_profiles');

        Schema::table('translation_glossary', function (Blueprint $table) {
            $table->dropColumn(['type', 'rule', 'context', 'active']);
        });

        Schema::table('ltm_translations', function (Blueprint $table) {
            $table->dropColumn(['source_hash', 'qa_status', 'qa_issues', 'pending_value', 'pending_job_id']);
        });
    }

    private function sourceLocale(): string
    {
        try {
            return app(\App\Services\Localization\LanguageRegistry::class)->sourceLocale();
        } catch (\Throwable $e) {
            return config('app.fallback_locale', 'en');
        }
    }
};
