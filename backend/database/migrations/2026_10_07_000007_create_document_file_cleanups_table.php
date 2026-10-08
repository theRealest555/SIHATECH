<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_file_cleanups', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->unique();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable()->index();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_file_cleanups');
    }
};
