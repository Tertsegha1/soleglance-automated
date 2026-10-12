<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Report $report)
    {
    }

    public function handle(ReportBuilder $builder): void
    {
        $pdf = $builder->pdf($this->report);
        $path = "reports/{$this->report->user_id}/report-{$this->report->id}.pdf";
        Storage::disk('local')->put($path, $pdf);

        $this->report->forceFill(['status' => 'ready', 'path' => $path, 'error' => null])->save();
    }

    public function failed(\Throwable $e): void
    {
        $this->report->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
    }
}
