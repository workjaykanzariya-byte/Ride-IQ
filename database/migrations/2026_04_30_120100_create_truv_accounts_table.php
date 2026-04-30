<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('truv_accounts')) {
            return;
        }

        Schema::create('truv_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('truv_account_id');
            $table->string('platform')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'truv_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truv_accounts');
    }
};
