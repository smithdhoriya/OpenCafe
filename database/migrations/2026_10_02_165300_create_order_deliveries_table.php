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
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            // Snapshot of the exact address used — survives edits/deletion of the saved address.
            $table->string('recipient_name', 150);
            $table->string('phone', 30);
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('distance_km', 6, 2)->unsigned();
            $table->foreignId('delivery_rate_id')->nullable()->constrained('delivery_rates')->nullOnDelete();
            // Frozen charge actually applied; never recalculated after creation.
            $table->decimal('delivery_charge', 10, 2)->unsigned();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('customer_address_id');
            $table->index('delivery_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');
    }
};
