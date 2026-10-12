<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LabelFeedback extends Model
{
    protected $table = 'label_feedback';

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'task', 'predicted', 'corrected'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Text that may leave the platform for retraining: public posts and news only. */
    public function shareableText(): ?string
    {
        $subject = $this->subject;

        return match (true) {
            $subject instanceof Post => $subject->visibility === \App\Enums\Visibility::Public && !$subject->hidden_at ? $subject->body : null,
            $subject instanceof Article => $subject->analysisText(),
            default => null,
        };
    }
}
