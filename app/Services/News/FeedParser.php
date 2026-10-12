<?php

namespace App\Services\News;

use Illuminate\Support\Carbon;

/**
 * Parses RSS 2.0 and Atom feeds into plain arrays.
 */
class FeedParser
{
    /**
     * @return list<array{guid: string, title: string, summary: ?string, url: string, published_at: ?Carbon}>
     */
    public function parse(string $xml, int $limit = 100): array
    {
        $previous = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch external resources while parsing.
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new \InvalidArgumentException('Feed is not valid XML.');
        }

        $items = [];

        if (isset($doc->channel->item)) {
            foreach ($doc->channel->item as $item) {
                $link = trim((string) $item->link);
                $items[] = [
                    'guid' => trim((string) ($item->guid ?: $link)),
                    'title' => $this->clean((string) $item->title),
                    'summary' => $this->clean((string) $item->description) ?: null,
                    'url' => $link,
                    'published_at' => $this->date((string) $item->pubDate),
                ];
            }
        } elseif ($doc->getName() === 'feed') {
            foreach ($doc->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) $l['rel'] === '' || (string) $l['rel'] === 'alternate') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $items[] = [
                    'guid' => trim((string) ($entry->id ?: $link)),
                    'title' => $this->clean((string) $entry->title),
                    'summary' => $this->clean((string) ($entry->summary ?: $entry->content)) ?: null,
                    'url' => $link,
                    'published_at' => $this->date((string) ($entry->published ?: $entry->updated)),
                ];
            }
        } else {
            throw new \InvalidArgumentException('Unsupported feed format.');
        }

        $items = array_filter($items, fn ($i) => $i['guid'] !== '' && $i['title'] !== ''
            && preg_match('#^https?://#i', $i['url']));

        return array_slice(array_values($items), 0, $limit);
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function date(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value)->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
