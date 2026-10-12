<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\AnalyticsUnavailable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a user run the sentiment model on their own text (SRS UC-007).
 */
class SentimentController extends Controller
{
    public function index(): View
    {
        return view('sentiment.index', ['result' => null, 'text' => '']);
    }

    public function analyse(Request $request, AnalyticsClient $analytics): View
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:2', 'max:2000']]);

        try {
            $result = $analytics->sentiment([$data['text']])[0];
            $error = null;
        } catch (AnalyticsUnavailable) {
            $result = null;
            $error = 'The sentiment service is unavailable right now. Please try again later.';
        }

        return view('sentiment.index', ['result' => $result, 'text' => $data['text'], 'error' => $error]);
    }
}
