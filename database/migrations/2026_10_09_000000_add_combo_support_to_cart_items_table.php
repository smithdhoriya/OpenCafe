<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Mirrors order_items: a line is either a menu item or a combo.
            // Application must ensure exactly one of menu_item_id/combo_id is
            // set per line; not enforceable as a portable DB constraint.
            $table->foreignId('menu_item_id')->nullable()->change();
            $table->foreignId('combo_id')->nullable()->after('menu_item_variant_id')->constrained('combos')->cascadeOnDelete();
            $table->index('combo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combo_id');
            $table->foreignId('menu_item_id')->nullable(false)->change();
        });
    }
};
