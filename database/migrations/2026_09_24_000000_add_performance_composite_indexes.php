<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPerformanceCompositeIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. product_badge_contents (targetable_type, targetable_id)
        if (Schema::hasTable('product_badge_contents')) {
            Schema::table('product_badge_contents', function (Blueprint $table) {
                if (!$this->hasIndex('product_badge_contents', 'pbc_targetable_composite_idx')) {
                    $table->index(['targetable_type', 'targetable_id'], 'pbc_targetable_composite_idx');
                }
            });
        }

        // 2. webinars composite indexes
        if (Schema::hasTable('webinars')) {
            Schema::table('webinars', function (Blueprint $table) {
                if (!$this->hasIndex('webinars', 'webinars_status_private_updated_idx')) {
                    $table->index(['status', 'private', 'updated_at'], 'webinars_status_private_updated_idx');
                }
                if (!$this->hasIndex('webinars', 'webinars_status_cat_updated_idx')) {
                    $table->index(['status', 'category_id', 'updated_at'], 'webinars_status_cat_updated_idx');
                }
            });
        }

        // 3. sales composite indexes
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!$this->hasIndex('sales', 'sales_buyer_access_refund_idx')) {
                    $table->index(['buyer_id', 'access_to_purchased_item', 'refund_at'], 'sales_buyer_access_refund_idx');
                }
            });
        }

        // 4. special_offers composite indexes
        if (Schema::hasTable('special_offers')) {
            Schema::table('special_offers', function (Blueprint $table) {
                if (!$this->hasIndex('special_offers', 'so_webinar_status_dates_idx')) {
                    $table->index(['webinar_id', 'status', 'from_date', 'to_date'], 'so_webinar_status_dates_idx');
                }
                if (!$this->hasIndex('special_offers', 'so_bundle_status_dates_idx')) {
                    $table->index(['bundle_id', 'status', 'from_date', 'to_date'], 'so_bundle_status_dates_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('product_badge_contents')) {
            Schema::table('product_badge_contents', function (Blueprint $table) {
                if ($this->hasIndex('product_badge_contents', 'pbc_targetable_composite_idx')) {
                    $table->dropIndex('pbc_targetable_composite_idx');
                }
            });
        }

        if (Schema::hasTable('webinars')) {
            Schema::table('webinars', function (Blueprint $table) {
                if ($this->hasIndex('webinars', 'webinars_status_private_updated_idx')) {
                    $table->dropIndex('webinars_status_private_updated_idx');
                }
                if ($this->hasIndex('webinars', 'webinars_status_cat_updated_idx')) {
                    $table->dropIndex('webinars_status_cat_updated_idx');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if ($this->hasIndex('sales', 'sales_buyer_access_refund_idx')) {
                    $table->dropIndex('sales_buyer_access_refund_idx');
                }
            });
        }

        if (Schema::hasTable('special_offers')) {
            Schema::table('special_offers', function (Blueprint $table) {
                if ($this->hasIndex('special_offers', 'so_webinar_status_dates_idx')) {
                    $table->dropIndex('so_webinar_status_dates_idx');
                }
                if ($this->hasIndex('special_offers', 'so_bundle_status_dates_idx')) {
                    $table->dropIndex('so_bundle_status_dates_idx');
                }
            });
        }
    }

    private function hasIndex($table, $indexName)
    {
        $indices = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
        return count($indices) > 0;
    }
}
