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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            // Null user_id = guest order; guest_* columns are required by application
            // validation in that case, not a NOT NULL constraint, since the same
            // columns are correctly null for logged-in orders.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name', 150)->nullable();
            $table->string('guest_email', 150)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method', 30)->nullable();
            $table->char('currency', 3);
            $table->decimal('subtotal', 10, 2)->unsigned()->default(0);
            $table->decimal('discount_total', 10, 2)->unsigned()->default(0);
            // Frozen at order-creation time from order_deliveries.delivery_charge;
            // never recalculated afterward even if delivery_rates/settings change.
            $table->decimal('delivery_charge', 10, 2)->unsigned()->default(0);
            $table->decimal('tax_total', 10, 2)->unsigned()->default(0);
            $table->decimal('total', 10, 2)->unsigned()->default(0);
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('offer_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
