<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_id')->constrained('bikes')->cascadeOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('mileage');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('legacy_id', 40)->nullable()->unique();
            $table->timestamps();

            // one reading per bike per day, and the index the dashboard leans on
            $table->unique(['bike_id', 'recorded_on']);
            $table->index(['bike_id', 'recorded_on', 'mileage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
