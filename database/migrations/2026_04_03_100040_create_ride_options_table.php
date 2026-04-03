<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained()->cascadeOnDelete()->index();
            $table->string('provider')->index();
            $table->string('service_type');
            $table->decimal('price', 10, 2);
            $table->integer('eta');
            $table->integer('duration');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['ride_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_options');
    }
};
