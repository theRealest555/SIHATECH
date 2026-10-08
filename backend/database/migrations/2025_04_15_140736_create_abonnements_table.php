<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Changed table name from 'subscription_plans' to 'abonnements' to match model
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            // Added 'semi-annual' to the enum based on common usage in seeders/docs
            $table->enum('billing_cycle', ['monthly', 'yearly', 'semi-annual']);
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('abonnements');
    }
};
