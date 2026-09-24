<?php

namespace App\Mixins\Lang;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TranslateService
{
    private string $translate_from;
    private string $translate_to;
    private $replace_from;
    private bool $isFolder;

    //setters
    public function from(string $from, $isFolder = true, $replaceFrom = null): self
    {
        $this->isFolder = $isFolder;
        $this->replace_from = $replaceFrom;
        $this->translate_from = $from;

        return $this;
    }

    public function to(string $to): self
    {
        $this->translate_to = $to;
        return $this;
    }

    // Main Translate

    public function translate(): void
    {
        set_time_limit(0);
        $files = $this->getLocalLangFiles();

        foreach ($files as $file) {
            $translatedData = $this->getTranslatedData($file);
            $this->filePutContent($translatedData, $file);
        }
    }

    // Files
    private function getLocalLangFiles(): array
    {
        if ($this->isFolder) {
            $this->existsLocalLangDir();
        }

        $this->existsLocalLangFiles();

        return $this->getFiles($this->getTranslateLocalPath());
    }

    private function getFiles(string $path = null): array
    {
        if ($this->isFolder) {
            return File::allFiles($path);
        }

        $file = new SplFileInfo($path, '', '');

        return [$file];
    }

    //Translation
    private function getTranslatedData(SplFileInfo $file): string
    {
        $content = include $file;
        $translated = $this->translateLangFiles($content);
        return $this->addPhpSyntax(var_export($translated, true));
    }

    private function setUpGoogleTranslate(): GoogleTranslate
    {
        return (new GoogleTranslate())
            ->setSource('en')
            ->setTarget($this->translate_to);
    }

    private function translateLangFiles(array $content): array
    {
        $google = $this->setUpGoogleTranslate();
        return $this->translateBatch($content, $google);
    }

    // Batch Translation
    private function translateBatch(array $content, $google): array
    {
        $flat = [];
       //Flatten Array
        $walker = function ($array, $prefix = '') use (&$walker, &$flat) {

            foreach ($array as $key => $value) {

                $newKey = $prefix === ''
                    ? $key
                    : $prefix . '.' . $key;

                if (is_array($value)) {

                    $walker($value, $newKey);

                } else {

                    $flat[$newKey] = $value;
                }
            }
        };

        $walker($content);

        //Chunk Translation
        $translatedFlat = [];

        $chunks = array_chunk($flat, 50, true);

        foreach ($chunks as $chunk) {

            $texts = [];
            $map = [];

            foreach ($chunk as $path => $text) {

                //Skip Empty
                if (empty($text)) {
                    $translatedFlat[$path] = $text;
                    continue;
                }

                // Skip Long Text
                if (strlen($text) > 4000) {
                    $translatedFlat[$path] = $text;
                    continue;
                }

                //Skip HTML
                if ($text !== strip_tags($text)) {
                    $translatedFlat[$path] = $text;
                    continue;
                }

               // Protect Variables
                preg_match_all('/:\w+/', $text, $matches);

                $placeholders = $matches[0] ?? [];

                $safeText = $text;

                foreach ($placeholders as $i => $placeholder) {

                    $safeText = str_replace(
                        $placeholder,
                        "__VAR_{$i}__",
                        $safeText
                    );
                }

                //Cache
                $cacheKey = 'tr_' .
                    $this->translate_to .
                    '_' .
                    md5($safeText);

                if (Cache::has($cacheKey)) {

                    $translatedFlat[$path] = Cache::get($cacheKey);

                    continue;
                }

                $texts[] = $safeText;

                $map[$path] = [
                    'original' => $text,
                    'placeholders' => $placeholders,
                    'cache' => $cacheKey,
                ];
            }

            //Single Request For Chunk
            if (!empty($texts)) {

                $separator = "\n|||###|||\n";

                $translatedTexts = $google->translate(
                    implode($separator, $texts)
                );

                $translatedTexts = explode(
                    $separator,
                    $translatedTexts
                );

                $index = 0;

                foreach ($map as $path => $meta) {

                    $translatedText =
                        $translatedTexts[$index]
                        ?? $meta['original'];

                    //Restore Variables
                    foreach ($meta['placeholders'] as $i => $placeholder) {

                        $translatedText = str_replace(
                            "__VAR_{$i}__",
                            $placeholder,
                            $translatedText
                        );
                    }

                    //Save Cache
                    Cache::put(
                        $meta['cache'],
                        $translatedText,
                        60 * 60 * 24 * 7
                    );

                    $translatedFlat[$path] = $translatedText;

                    $index++;
                }
            }
        }

        // Rebuild Array
        $result = [];

        foreach ($translatedFlat as $path => $value) {

            data_set($result, $path, $value);
        }

        return $result;
    }

   //Save Files
    private function filePutContent(string $translatedData, $file): void
    {
        $baseFolder = lang_path($this->translate_to);

        if (!File::exists($baseFolder)) {

            File::makeDirectory(
                $baseFolder,
                0777,
                true,
                true
            );
        }

        $sourceBasePath = realpath(lang_path('en'));

        $currentPath = realpath($file->getPath());

        $relativePath = str_replace(
            $sourceBasePath,
            '',
            $currentPath
        );

        $folderPath = $baseFolder . $relativePath;

        if (!File::exists($folderPath)) {

            File::makeDirectory(
                $folderPath,
                0777,
                true,
                true
            );
        }

        $filePath =
            $folderPath .
            DIRECTORY_SEPARATOR .
            $file->getFilename();

        File::put($filePath, $translatedData);
    }

    //Helpers
    private function addPhpSyntax(string $translatedData): string
    {
        return '<?php return ' . $translatedData . ';';
    }

    private function getTranslateLocalPath(): string
    {
        return lang_path(
            DIRECTORY_SEPARATOR .
            $this->translate_from
        );
    }

    // Exceptions
    private function existsLocalLangDir(): void
    {
        $path = $this->getTranslateLocalPath();

        throw_if(
            !File::isDirectory($path),
            "lang folder {$this->translate_from} not Exist!"
        );
    }

    private function existsLocalLangFiles(): void
    {
        $files = $this->getFiles(
            $this->getTranslateLocalPath()
        );

        throw_if(
            empty($files),
            "lang files in '{$this->translate_from}' folder not found!"
        );
    }
}