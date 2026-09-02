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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            
            $table->string('gateway');
            
            $table->string('order_id')->nullable()->unique();

            $table->unsignedBigInteger('customer_id');

            $table->foreign('customer_id')
                  ->references('id')
                  ->on('payment_customers')
                  ->onUpdate('CASCADE')
                  ->onDelete('CASCADE');

            $table->string('gateway_reference')->nullable()->unique();
            
            $table->unsignedBigInteger('minor_amount');
            
            $table->unsignedBigInteger('remain_minor_amount')->default(0);
            
            $table->string('currency', 3);

            $table->string('source');
            
            $table->string('source_subtype')->nullable();
            
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
        Schema::dropIfExists('payment_transactions');
    }
};
