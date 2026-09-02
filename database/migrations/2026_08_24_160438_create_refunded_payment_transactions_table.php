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
        Schema::create('refunded_payment_transactions', function (Blueprint $table) {

            $table->id();
            
            $table->string('parent_transaction');

            $table->foreign('parent_transaction')
            ->references('gateway_reference')
            ->on('payment_transactions')
            ->onUpdate('cascade')
            ->onDelete('cascade');

            $table->string('order_id')->nullable();

            $table->string('transaction_id')->unique();

            $table->unsignedBigInteger('minor_amount');

            $table->string('currency', 3);

            $table->enum('refund_type', ['partially_refunded', 'fully_refunded'])->default('fully_refunded');

            $table->string('status');
            
            $table->json('meta_data');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunded_payment_transactions');
    }
};
