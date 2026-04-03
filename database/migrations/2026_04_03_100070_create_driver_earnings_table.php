<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->date('date');
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->unsignedInteger('total_trips')->default(0);
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_earnings');
    }
};
