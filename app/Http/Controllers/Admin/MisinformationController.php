<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelFeedback;
use App\Models\MisinformationReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Human review of posts the model flags as possible health misinformation.
 * Confirming attaches a correction note to the post; it is not removed
 * automatically. Every decision is also recorded as training feedback.
 */
class MisinformationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('admin.misinformation', [
            'reviews' => MisinformationReview::with(['post.user:id,name,username', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('score')->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function update(Request $request, MisinformationReview $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'dismissed'])],
            'correction' => ['nullable', 'required_if:status,confirmed', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url:https,http', 'max:500'],
        ]);

        $review->fill($data);
        $review->reviewer_id = $request->user()->id;
        $review->reviewed_at = now();
        $review->save();

        $fb = LabelFeedback::firstOrNew([
            'user_id' => $request->user()->id,
            'subject_type' => $review->post->getMorphClass(),
            'subject_id' => $review->post_id,
            'task' => 'misinformation',
        ]);
        $fb->fill(['predicted' => 'false', 'corrected' => $data['status'] === 'confirmed' ? 'false' : 'true']);
        $fb->sent_at = null;
        $fb->save();

        return back()->with('status', $data['status'] === 'confirmed' ? 'Marked as misinformation; correction shown on the post.' : 'Flag dismissed.');
    }
}
