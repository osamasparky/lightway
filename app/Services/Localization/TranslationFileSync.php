<?php

namespace App\Services\Localization;

use App\Models\Localization\TranslationEntry;
use Barryvdh\TranslationManager\Manager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Moves translations between the language files (what the site reads) and the
 * working copy in the database (what the Translation Manager edits).
 * Wraps the translation-manager Manager so every existing function keeps working.
 */
class TranslationFileSync
{
    /** Legacy unpublished values replaced by a sync (written to storage for recovery). */
    private array $backup = [];

    public function __construct(
        private Manager $manager,
        private TranslationCatalog $catalog
    ) {
    }

    /**
     * Files -> database. Append mode only adds keys the database doesn't have yet;
     * replace mode overwrites database values with the file values.
     */
    public function import(bool $replace = false, ?string $group = null): int
    {
        $counter = $this->manager->importTranslations($replace, null, $group ?: false);

        if (!$replace) {
            $counter += $this->syncFromFiles($group);
        }

        $this->catalog->flushStats();

        return (int)$counter;
    }

    /**
     * Brings the working copy up to date with the language files.
     *
     * The files are what the website reads, so they are the truth for every row that has
     * no pending edit in the Translation Manager. Rows edited here and not yet published
     * through this manager (source manual / ai) keep their value — those are the edits a
     * publish should write. Replaced legacy values are saved to storage/app/localization.
     * Without this, stale database values would overwrite newer file translations on
     * publish, and strings translated only in the files would show as missing.
     */
    public function syncFromFiles(?string $onlyGroup = null): int
    {
        $langPath = app()->langPath();
        $filled = 0;

        foreach (File::directories($langPath) as $localeDir) {
            $locale = basename($localeDir);
            if ($locale === 'vendor') {
                continue;
            }

            foreach (File::allFiles($localeDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = trim(str_replace('\\', '/', $file->getRelativePath()), '/');
                $group = ($relative !== '' ? $relative . '/' : '') . $file->getFilenameWithoutExtension();

                if ($onlyGroup and $onlyGroup !== $group) {
                    continue;
                }

                // Pending edits made through this Translation Manager (source manual / ai) are
                // kept. "Changed" rows without a source predate it (old machine translations
                // that were never published) and follow the file like everything else.
                $rows = TranslationEntry::where('locale', $locale)
                    ->where('group', $group)
                    ->where(function ($q) {
                        $q->where('status', TranslationEntry::STATUS_SAVED)
                            ->orWhereNull('source')
                            ->orWhereNull('value')
                            ->orWhere('value', '');
                    })
                    ->get(['id', 'key', 'value', 'status', 'source']);

                if ($rows->isEmpty()) {
                    continue;
                }

                $values = include $file->getRealPath();
                if (!is_array($values)) {
                    continue;
                }
                $values = Arr::dot($values);

                foreach ($rows as $row) {
                    $value = $values[$row->key] ?? null;

                    if (!is_string($value) or trim($value) === '') {
                        continue;
                    }

                    if ($value === $row->value) {
                        // Same text as the file: nothing is really waiting to be published.
                        if ((int)$row->status === TranslationEntry::STATUS_CHANGED) {
                            TranslationEntry::where('id', $row->id)->update(['status' => TranslationEntry::STATUS_SAVED]);
                        }
                        continue;
                    }

                    if ((int)$row->status === TranslationEntry::STATUS_CHANGED and $row->value !== null and $row->value !== '') {
                        $this->backup[] = ['locale' => $locale, 'group' => $group, 'key' => $row->key, 'database' => $row->value, 'file' => $value];
                    }

                    // The file changed outside the manager: take its text and drop labels
                    // (AI / reviewed) that described the old text.
                    TranslationEntry::where('id', $row->id)->update([
                        'value' => $value,
                        'status' => TranslationEntry::STATUS_SAVED,
                        'source' => null,
                        'review_status' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
                    $filled++;
                }
            }
        }

        if (count($this->backup)) {
            File::ensureDirectoryExists(storage_path('app/localization'));
            File::put(
                storage_path('app/localization/replaced-unpublished-' . now()->format('Ymd-His') . '.json'),
                json_encode($this->backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $this->backup = [];
        }

        return $filled;
    }

    /**
     * Database -> files for one group ('*' = all groups).
     * Always appends keys that exist only in the file first, so a publish can never
     * delete strings that were added to the files directly.
     */
    public function publish(string $group): void
    {
        if ($group === '*') {
            $this->manager->importTranslations(false);
            $this->syncFromFiles();
            $this->manager->exportAllTranslations();
        } else {
            $this->manager->importTranslations(false, null, $group);
            $this->syncFromFiles($group);
            $this->manager->exportTranslations($group);
        }

        $this->catalog->flushStats();
    }

    /** Groups with edits that haven't been written to the files yet. */
    public function unpublishedGroups(): array
    {
        return TranslationEntry::where('status', TranslationEntry::STATUS_CHANGED)
            ->selectRaw('`group`, count(*) as changes')
            ->groupBy('group')
            ->orderBy('group')
            ->pluck('changes', 'group')
            ->all();
    }

    /** Scan the code for trans()/__() calls and add unknown keys. */
    public function findInCode(): int
    {
        $found = (int)$this->manager->findTranslations();
        $this->catalog->flushStats();

        return $found;
    }

    /** Add empty keys to a group (new or existing), one per line. */
    public function addKeys(string $group, array $keys): int
    {
        $added = 0;

        foreach ($keys as $key) {
            $key = trim($key);
            if ($key !== '') {
                $this->manager->missingKey('*', $group, $key);
                $added++;
            }
        }

        $this->catalog->flushStats();

        return $added;
    }

    public function deleteKey(string $group, string $key): int
    {
        $deleted = TranslationEntry::where('key_hash', TranslationEntry::hashFor($group, $key))->delete();
        $this->catalog->flushStats();

        return $deleted;
    }

    public function locales(): array
    {
        return $this->manager->getLocales();
    }

    /** Create the language folder so the language can be translated and published. */
    public function addLocale(string $locale): void
    {
        $this->manager->addLocale($locale);
    }

    /** Remove a language from the working copy (the language files are not deleted). */
    public function removeLocale(string $locale): void
    {
        $this->manager->removeLocale($locale);
        $this->catalog->flushStats();
    }

    public static function sanitizeName(?string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\/]/', '', (string)$name);
    }
}
