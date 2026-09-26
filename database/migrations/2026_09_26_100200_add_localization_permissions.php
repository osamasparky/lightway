<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Granular Translation Manager permissions in the existing sections/permissions system.
 * Roles that can already open the Translation Manager (section 3133) get them all,
 * so nobody loses what they can do today.
 */
return new class extends Migration {
    private array $sections = [
        3134 => ['admin_translation_manager_edit', 'Edit translations'],
        3135 => ['admin_translation_manager_review', 'Review translations'],
        3136 => ['admin_translation_manager_ai', 'Translate with AI'],
        3137 => ['admin_translation_manager_settings', 'AI settings & glossary'],
        3138 => ['admin_translation_manager_delete', 'Delete keys & languages'],
    ];

    public function up()
    {
        foreach ($this->sections as $id => [$name, $caption]) {
            DB::table('sections')->updateOrInsert(
                ['id' => $id],
                ['name' => $name, 'section_group_id' => 3132, 'caption' => $caption, 'type' => 'admin']
            );
        }

        $roleIds = DB::table('permissions')->where('section_id', 3133)->where('allow', true)->pluck('role_id');

        foreach ($roleIds as $roleId) {
            foreach (array_keys($this->sections) as $sectionId) {
                DB::table('permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'section_id' => $sectionId],
                    ['allow' => true]
                );
            }
        }

        Cache::forget('sections');
    }

    public function down()
    {
        DB::table('permissions')->whereIn('section_id', array_keys($this->sections))->delete();
        DB::table('sections')->whereIn('id', array_keys($this->sections))->delete();
        Cache::forget('sections');
    }
};
