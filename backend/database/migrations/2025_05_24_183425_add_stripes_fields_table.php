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
        // Add Stripe customer ID to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('provider_id');
            $table->index('stripe_customer_id');
        });

        // Add Stripe price ID to abonnements table
        Schema::table('abonnements', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('features');
            $table->index('stripe_price_id');
        });

        // Add invoice URL to payments table (for Stripe invoices)
        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_url')->nullable()->after('payment_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropIndex(['stripe_price_id']);
            $table->dropColumn('stripe_price_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('invoice_url');
        });
    }
};
