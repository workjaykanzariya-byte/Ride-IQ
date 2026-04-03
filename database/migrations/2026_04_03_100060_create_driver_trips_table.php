<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->index();
            $table->string('provider')->index();
            $table->string('trip_id_external');
            $table->decimal('earnings', 10, 2);
            $table->decimal('distance', 10, 2)->nullable();
            $table->integer('duration')->nullable();
            $table->date('trip_date')->index();
            $table->json('meta')->nullable();
            $table->unique(['provider', 'trip_id_external']);
            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trips');
    }
};
