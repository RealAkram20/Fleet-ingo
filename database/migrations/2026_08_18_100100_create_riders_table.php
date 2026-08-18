<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('license_number', 60)->nullable();
            $table->date('license_expiry')->nullable();
            $table->boolean('is_active')->default(true);


            $table->timestamps();
            $table->softDeletes();

            $table->index('license_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
