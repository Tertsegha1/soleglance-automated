<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\LabelFeedback;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Is this label right?" Users can correct sentiment on content they can see;
 * health labels can only be corrected by health officers and admins.
 */
class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['post', 'article'])],
            'subject_id' => ['required', 'integer'],
            'task' => ['required', Rule::in(['sentiment', 'health_mention', 'misinformation'])],
            'corrected' => ['required', 'string', 'max:20'],
        ]);

        $allowed = match ($data['task']) {
            'sentiment' => ['positive', 'neutral', 'negative'],
            'health_mention' => ['health', 'other'],
            'misinformation' => ['false', 'true'],
        };
        abort_unless(in_array($data['corrected'], $allowed, true), 422, 'Invalid label for this task.');
        if ($data['task'] !== 'sentiment') {
            Gate::authorize('health');
        }

        $subject = $data['subject_type'] === 'post' ? Post::findOrFail($data['subject_id']) : Article::findOrFail($data['subject_id']);
        if ($subject instanceof Post) {
            Gate::authorize('view', $subject);
        }

        $predicted = $data['task'] === 'sentiment' ? $subject->sentiment?->value : null;
        $fb = LabelFeedback::firstOrNew([
            'user_id' => $request->user()->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'task' => $data['task'],
        ]);
        $fb->fill(['predicted' => $predicted, 'corrected' => $data['corrected']]);
        $fb->sent_at = null;
        $fb->save();

        return back()->with('status', 'Thanks, your correction helps improve the model.');
    }
}
