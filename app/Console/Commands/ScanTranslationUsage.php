<?php

namespace App\Console\Commands;

use App\Models\Localization\TranslationKeyUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/**
 * Records where each translation key is used (file:line) so translators see context.
 */
class ScanTranslationUsage extends Command
{
    protected $signature = 'localization:scan-usage';
    protected $description = 'Index where translation keys are used in views and code';

    private const PATTERN = <<<'REGEX'
/(?:\btrans|\b__|\btrans_choice|@lang|@choice|Lang::get|Lang::choice)\(\s*["']([A-Za-z0-9_\-\/]+)\.([^"'\n]+?)["']\s*[,)]/
REGEX;

    public function handle(): int
    {
        $base = str_replace('\\', '/', base_path()) . '/';

        $finder = (new Finder())->files()
            ->in([base_path('app'), base_path('resources/views'), base_path('routes')])
            ->name('*.php')
            ->ignoreDotFiles(true);

        $rows = [];
        $now = now();

        foreach ($finder as $file) {
            $path = str_replace('\\', '/', $file->getRealPath());
            $relative = str_starts_with($path, $base) ? substr($path, strlen($base)) : $file->getRelativePathname();

            foreach (file($file->getRealPath()) as $index => $line) {
                if (!preg_match_all(self::PATTERN, $line, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $rows[] = [
                        'key_hash' => sha1($match[1] . '|' . $match[2]),
                        'file' => mb_substr($relative, 0, 255),
                        'line' => $index + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        DB::transaction(function () use ($rows) {
            TranslationKeyUsage::query()->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                TranslationKeyUsage::insert($chunk);
            }
        });

        $this->info(count($rows) . ' usages indexed.');

        return self::SUCCESS;
    }
}
