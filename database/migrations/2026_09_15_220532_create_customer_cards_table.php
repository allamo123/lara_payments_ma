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
        Schema::create('customer_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('payment_customers')
                ->cascadeOnDelete();

            $table->string('gateway');
            $table->string('gateway_card_id')->nullable();

            $table->text('token');

            $table->string('brand');
            $table->string('last_four', 4);

            $table->unsignedTinyInteger('expiry_month');
            $table->unsignedSmallInteger('expiry_year');

            $table->string('cardholder_name');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'gateway']);
            $table->unique(['gateway', 'gateway_card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cards');
    }
};
