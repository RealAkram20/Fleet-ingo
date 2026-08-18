<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bikes', function (Blueprint $table) {
            $table->id();
            $table->string('reg', 30)->unique();
            $table->string('model')->nullable();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();

            // service is due on distance OR time, whichever comes first
            $table->unsignedInteger('service_interval_km')->default(3000);
            $table->unsignedSmallInteger('service_interval_months')->nullable()->default(6);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bikes');
    }
};
