<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('feed_url')->unique();
            $table->string('category', 32)->nullable(); // default category for items
            $table->string('country', 2)->nullable();
            $table->string('language', 12)->default('en');
            $table->boolean('active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')->constrained()->cascadeOnDelete();
            $table->string('guid', 191);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->string('url', 2048);
            $table->timestamp('published_at')->nullable()->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('language', 12)->nullable();
            $table->string('category', 32)->nullable()->index();
            $table->string('sentiment', 8)->nullable()->index();
            $table->float('sentiment_score')->nullable();
            $table->json('sentiment_scores')->nullable();
            $table->string('sentiment_model', 64)->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->timestamps();
            $table->unique(['news_source_id', 'guid']);
        });

        // Snapshot of trending terms computed by the analytics service.
        Schema::create('trends', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->string('category', 32)->nullable()->index();
            $table->timestamp('window_start');
            $table->timestamp('window_end')->index();
            $table->unsignedInteger('mentions');
            $table->float('baseline');
            $table->float('score')->index();
            $table->unsignedInteger('positive')->default(0);
            $table->unsignedInteger('neutral')->default(0);
            $table->unsignedInteger('negative')->default(0);
            $table->float('mean_sentiment')->nullable();
            $table->json('sample')->nullable(); // [{type: post|article, id}]
            $table->timestamps();
            $table->index(['window_end', 'score']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 100);
            $table->date('from');
            $table->date('to');
            $table->string('country', 2)->nullable();
            $table->string('status', 12)->default('pending'); // pending | ready | failed
            $table->string('path')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('target_user_id')->nullable()->index();
            $table->unsignedBigInteger('target_post_id')->nullable()->index();
            $table->string('action', 32);
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('trends');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('news_sources');
    }
};
