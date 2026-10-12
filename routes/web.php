<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SentimentController;
use App\Http\Controllers\TrendingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('feed') : view('welcome'))->name('home');
Route::get('/avatar/{username}.svg', [UserController::class, 'avatar'])->name('avatar');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed');
    Route::redirect('/dashboard', '/feed')->name('dashboard');

    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:writes')->name('posts.store');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/reactions', [ReactionController::class, 'store'])->middleware('throttle:messages')->name('posts.react');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:writes')->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('/@{username}', [UserController::class, 'show'])->where('username', '[A-Za-z0-9_]+')->name('users.show');

    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{user}', [FriendController::class, 'store'])->middleware('throttle:writes')->name('friends.store');
    Route::post('/friends/{user}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::post('/friends/{user}/decline', [FriendController::class, 'decline'])->name('friends.decline');
    Route::delete('/friends/{user}', [FriendController::class, 'destroy'])->name('friends.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/messages', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/messages/with/{user}', [ChatController::class, 'with'])->name('chat.with');
    Route::get('/messages/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/messages/{conversation}', [ChatController::class, 'send'])->middleware('throttle:messages')->name('chat.send');
    Route::post('/messages/{conversation}/read', [ChatController::class, 'markRead'])->name('chat.read');
    Route::delete('/messages/{conversation}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chat.messages.destroy');
    Route::delete('/messages/{conversation}', [ChatController::class, 'clear'])->name('chat.clear');

    Route::get('/search', SearchController::class)->name('search');
    Route::get('/trending', [TrendingController::class, 'index'])->name('trending.index');
    Route::get('/topic', [TrendingController::class, 'show'])->name('trending.show');
    Route::get('/sentiment', [SentimentController::class, 'index'])->name('sentiment.index');
    Route::post('/sentiment', [SentimentController::class, 'analyse'])->middleware('throttle:writes')->name('sentiment.analyse');

    Route::get('/health', [HealthController::class, 'index'])->name('health.index');
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:writes')->name('feedback.store');

    Route::prefix('admin/health')->name('admin.')->middleware('can:health')->group(function () {
        Route::get('/alerts', [Admin\HealthAlertController::class, 'index'])->name('health-alerts.index');
        Route::patch('/alerts/{alert}', [Admin\HealthAlertController::class, 'update'])->name('health-alerts.update');
        Route::get('/misinformation', [Admin\MisinformationController::class, 'index'])->name('misinformation.index');
        Route::patch('/misinformation/{review}', [Admin\MisinformationController::class, 'update'])->name('misinformation.update');
    });

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/unsuspend', [Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/posts', [Admin\PostController::class, 'index'])->name('posts.index');
        Route::post('/posts/{post}/hide', [Admin\PostController::class, 'hide'])->name('posts.hide');
        Route::post('/posts/{post}/unhide', [Admin\PostController::class, 'unhide'])->name('posts.unhide');
        Route::get('/news-sources', [Admin\NewsSourceController::class, 'index'])->name('news.index');
        Route::post('/news-sources', [Admin\NewsSourceController::class, 'store'])->name('news.store');
        Route::post('/news-sources/{source}/toggle', [Admin\NewsSourceController::class, 'toggle'])->name('news.toggle');
        Route::delete('/news-sources/{source}', [Admin\NewsSourceController::class, 'destroy'])->name('news.destroy');
        Route::get('/audit', Admin\AuditController::class)->name('audit');
        Route::get('/models', [Admin\ModelOpsController::class, 'index'])->name('models');
        Route::post('/models/retrain', [Admin\ModelOpsController::class, 'retrain'])->name('models.retrain');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
