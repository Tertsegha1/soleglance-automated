<?php

use App\Jobs\FetchNewsSource;
use App\Models\Article;
use App\Models\NewsSource;
use App\Services\News\FeedParser;
use Illuminate\Support\Facades\Http;

$rss = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel><title>Test</title>
<item><title>First &amp; foremost</title><link>https://example.test/1</link><guid>g1</guid><description><![CDATA[<p>Hello <b>world</b></p>]]></description><pubDate>Mon, 05 Oct 2026 10:00:00 GMT</pubDate></item>
<item><title>Bad link</title><link>javascript:alert(1)</link><guid>g2</guid></item>
</channel></rss>
XML;

$atom = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"><title>A</title>
<entry><title>Atom entry</title><link rel="alternate" href="https://example.test/a"/><id>urn:a1</id><updated>2026-10-05T10:00:00Z</updated><summary>Sum</summary></entry>
</feed>
XML;

test('RSS is parsed, cleaned and unsafe links dropped', function () use ($rss) {
    $items = (new FeedParser)->parse($rss);
    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('First & foremost')
        ->and($items[0]['summary'])->toBe('Hello world')
        ->and($items[0]['published_at']->toIso8601String())->toBe('2026-10-05T10:00:00+00:00');
});

test('Atom is parsed', function () use ($atom) {
    $items = (new FeedParser)->parse($atom);
    expect($items[0]['url'])->toBe('https://example.test/a')->and($items[0]['guid'])->toBe('urn:a1');
});

test('external entities are not expanded', function () {
    $xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&x;</title><link>https://e.test</link><guid>1</guid></item></channel></rss>';
    $items = (new FeedParser)->parse($xxe);
    expect($items)->toBe([]);
});

test('fetching a source stores articles once', function () use ($rss) {
    Http::fake(['news.example.test/*' => Http::response($rss)]);
    $source = NewsSource::factory()->create(['feed_url' => 'https://news.example.test/feed.xml', 'category' => 'politics']);

    (new FetchNewsSource($source))->handle(new FeedParser);
    (new FetchNewsSource($source))->handle(new FeedParser);

    expect(Article::count())->toBe(1)
        ->and(Article::sole()->category->value)->toBe('politics')
        ->and($source->fresh()->last_error)->toBeNull();
});

test('fetch errors are recorded on the source', function () {
    Http::fake(['news.example.test/*' => Http::response('nope', 500)]);
    $source = NewsSource::factory()->create(['feed_url' => 'https://news.example.test/feed.xml']);

    (new FetchNewsSource($source))->handle(new FeedParser);

    expect($source->fresh()->last_error)->not->toBeNull();
});
