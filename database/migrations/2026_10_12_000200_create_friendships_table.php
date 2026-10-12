<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per relationship. user_low_id/user_high_id hold the pair in a
        // canonical order so the unique index blocks duplicate or mirrored requests.
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('user_low_id');
            $table->unsignedBigInteger('user_high_id');
            $table->string('status', 10)->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_low_id', 'user_high_id']);
            $table->index(['addressee_id', 'status']);
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
