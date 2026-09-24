<?php

namespace App\TranslationManager;

class Manager extends \Barryvdh\TranslationManager\Manager
{
  public function exportTranslations($group = null, $json = false)
  {
    set_time_limit(0);
    ini_set('max_execution_time', 0);
    
    $group = trim($group, '/');

    $basePath = $this->app['path.lang'];

    if (! is_null($group) && ! $json) {

      if (! in_array($group, $this->config['exclude_groups'])) {

        $vendor = false;

        if ($group == '*') {
          return $this->exportAllTranslations();
        } else {
          if (\Illuminate\Support\Str::startsWith($group, 'vendor')) {
            $vendor = true;
          }
        }

        $models = [];

        \Barryvdh\TranslationManager\Models\Translation::ofTranslatedGroup($group)
          ->orderByGroupKeys(\Illuminate\Support\Arr::get($this->config, 'sort_keys', false))
          ->chunkById(50000, function ($chunk) use (&$models) {
            $models = array_merge($models, $chunk->all());
          });

        $tree = $this->makeTree($models);

        foreach ($tree as $locale => $groups) {

          $locale = basename($locale);

          if (isset($groups[$group])) {

            $translations = $groups[$group];

            $path = $this->app['path.lang'];

            $locale_path = $locale . DIRECTORY_SEPARATOR . $group;

            if ($vendor) {
              $path = $basePath . '/' . $group . '/' . $locale;
              $locale_path = \Illuminate\Support\Str::after($group, '/');
            }

            $subfolders = explode('/', $locale_path);

            array_pop($subfolders);

            $subfolder_level = '';

            foreach ($subfolders as $subfolder) {

              $subfolder_level .= $subfolder . DIRECTORY_SEPARATOR;

              $temp_path = rtrim(
                $path . DIRECTORY_SEPARATOR . $subfolder_level,
                DIRECTORY_SEPARATOR
              );

              if (! is_dir($temp_path)) {
                mkdir($temp_path, 0777, true);
              }
            }

            if ($vendor) {
              $path = $path . DIRECTORY_SEPARATOR . 'messages.php';
            } else {
              $path = $path . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $group . '.php';
            }

            $output = "<?php\n\nreturn " . var_export($translations, true) . ';' . PHP_EOL;

            $this->files->put($path, $output);
          }
        }

        \Barryvdh\TranslationManager\Models\Translation::ofTranslatedGroup($group)
          ->update([
            'status' => \Barryvdh\TranslationManager\Models\Translation::STATUS_SAVED
          ]);
      }
    }

    $this->events->dispatch(
      new \Barryvdh\TranslationManager\Events\TranslationsExportedEvent()
    );
  }
  public function importTranslations($replace = false, $base = null, $import_group = false)
  {
    set_time_limit(0);
    ini_set('max_execution_time', 0);

    $counter = 0;

    $vendor = true;

    if ($base == null) {
      $base = $this->app['path.lang'];
      $vendor = false;
    }

    foreach ($this->files->directories($base) as $langPath) {

      $locale = basename($langPath);

      if ($locale == 'vendor') {

        foreach ($this->files->directories($langPath) as $vendor) {
          $counter += $this->importTranslations($replace, $vendor);
        }

        continue;
      }

      $vendorName = $this->files->name(
        $this->files->dirname($langPath)
      );

      foreach ($this->files->allFiles($langPath) as $file) {

        $info = pathinfo($file);

        $group = $info['filename'];

        if ($import_group && $import_group !== $group) {
          continue;
        }

        if (in_array($group, $this->config['exclude_groups'])) {
          continue;
        }

        $directory = str_replace('\\', '/', dirname($file));
        $langPathNormalized = str_replace('\\', '/', $langPath);

        $relativePath = trim(
            str_replace($langPathNormalized, '', $directory),
            '/'
        );

        if (!empty($relativePath)) {
            $group = $relativePath . '/' . $group;
        }
        
        if (! $vendor) {
          $translations = include $file;
        } else {
          $translations = include $file;
          $group = 'vendor/' . $vendorName;
        }

        if ($translations && is_array($translations)) {

          foreach (\Illuminate\Support\Arr::dot($translations) as $key => $value) {

            $importedTranslation = $this->importTranslation(
              $key,
              $value,
              $locale,
              $group,
              $replace
            );

            $counter += $importedTranslation ? 1 : 0;
          }
        }
      }
    }

    return $counter;
  }
}
