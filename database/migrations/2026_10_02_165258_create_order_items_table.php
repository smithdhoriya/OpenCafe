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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Traceability only — nullOnDelete() so the order survives deletion of the
            // catalog row. Application must ensure exactly one of menu_item_id/combo_id
            // is set per line; not enforceable as a portable DB constraint.
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->foreignId('menu_item_variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained('combos')->nullOnDelete();
            // Snapshots frozen at order time — the historical record, independent of
            // later changes to the referenced menu item/variant/combo.
            $table->string('item_name', 150);
            $table->string('variant_name', 100)->nullable();
            // Frozen combo composition ({name, variant, quantity}[]) at order time, since
            // combo_items composition can change after the fact and must not alter history.
            $table->json('combo_components_snapshot')->nullable();
            $table->decimal('unit_price', 10, 2)->unsigned();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2)->unsigned();
            $table->text('special_instructions')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
