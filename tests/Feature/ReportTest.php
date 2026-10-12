<?php

use App\Jobs\GenerateReport;
use App\Models\Article;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('requesting a report queues generation', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/reports', ['topic' => 'budget', 'from' => now()->subWeek()->toDateString(), 'to' => now()->toDateString()])
        ->assertRedirect('/reports');

    Queue::assertPushed(GenerateReport::class);
});

test('generated report is a PDF downloadable only by its owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Article::factory()->create(['title' => 'Budget debate', 'sentiment' => 'negative', 'sentiment_score' => 0.8, 'published_at' => now()->subDay()]);

    $report = new Report(['topic' => 'budget', 'from' => now()->subWeek(), 'to' => now()]);
    $report->user()->associate($user);
    $report->save();

    (new GenerateReport($report))->handle(app(\App\Services\Reports\ReportBuilder::class));
    $report->refresh();

    expect($report->status)->toBe('ready');
    expect(substr(Storage::disk('local')->get($report->path), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->create())->get(route('reports.download', $report))->assertForbidden();
    $this->actingAs($user)->get(route('reports.download', $report))->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('report ranges are validated', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/reports', ['topic' => 'x', 'from' => now()->toDateString(), 'to' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors('to');
});
