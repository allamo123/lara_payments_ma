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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('gateway');

            $table->string('gateway_subscription_id')->unique();

             $table->foreignId('customer_id')
                  ->constrained('payment_customers', 'id')
                  ->onUpdate('CASCADE')
                  ->onDelete('CASCADE');

            $table->foreignId('plan_id')
                  ->constrained('subscription_plans', 'id')
                  ->onUpdate('CASCADE')
                  ->onDelete('CASCADE');


            $table->date('next_billing')->nullable();

            $table->date('starts_at')->nullable();

            $table->date('ends_at')->nullable();

            $table->date('reminder_date')->nullable();

            $table->date('suspended_at')->nullable();

            $table->date('resumed_at')->nullable();

            $table->date('reactivated_at')->nullable();

            $table->enum('status', [
                'active', 'disabled', 'suspended', 'pending'
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
