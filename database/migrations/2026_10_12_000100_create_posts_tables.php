<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility', 16)->default('public')->index();
            $table->string('country', 2)->nullable()->index();
            // Filled asynchronously by the analytics service.
            $table->string('language', 12)->nullable();
            $table->string('sentiment', 8)->nullable()->index();
            $table->float('sentiment_score')->nullable();
            $table->json('sentiment_scores')->nullable();
            $table->string('sentiment_model', 64)->nullable();
            $table->string('category', 32)->nullable()->index();
            $table->timestamp('analysed_at')->nullable();
            // Moderation
            $table->timestamp('hidden_at')->nullable()->index();
            $table->string('hidden_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8); // image | video
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        // Explicit audience for posts with visibility = custom.
        Schema::create('post_audience', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'user_id']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8);
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('sentiment', 8)->nullable();
            $table->float('sentiment_score')->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('post_audience');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
