<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per analysed post/article that the health model judged to be about health.
        Schema::create('health_mentions', function (Blueprint $table) {
            $table->id();
            $table->morphs('mentionable');
            $table->boolean('is_public')->default(true)->index();
            $table->float('health_score');
            $table->json('diseases');
            $table->json('symptoms');
            $table->json('regions');
            $table->string('disease', 40)->nullable()->index();   // first disease, for grouping
            $table->string('country', 2)->nullable()->index();
            $table->string('region', 8)->nullable()->index();
            $table->float('misinformation_score')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->unique(['mentionable_type', 'mentionable_id']);
            $table->index(['disease', 'country', 'occurred_at']);
        });

        Schema::create('health_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('disease', 40);
            $table->string('country', 2);
            $table->string('region', 8)->nullable();
            $table->string('method', 10);
            $table->float('statistic');
            $table->unsignedInteger('count');
            $table->float('baseline_mean')->nullable();
            $table->date('window_start');
            $table->date('window_end');
            $table->string('status', 16)->default('open')->index(); // open | acknowledged | resolved | dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('webhook_sent_at')->nullable();
            $table->timestamps();
            $table->index(['disease', 'country', 'region', 'status']);
        });

        Schema::create('misinformation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->float('score');
            $table->string('status', 16)->default('pending')->index(); // pending | confirmed | dismissed
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('label_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('task', 20); // sentiment | health_mention | misinformation
            $table->string('predicted', 20)->nullable();
            $table->string('corrected', 20);
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_id', 'task']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_feedback');
        Schema::dropIfExists('misinformation_reviews');
        Schema::dropIfExists('health_alerts');
        Schema::dropIfExists('health_mentions');
    }
};
