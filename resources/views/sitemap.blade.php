{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
    </url>
@foreach ($categories as $category)
    <url>
        <loc>{{ route('categories.show', $category) }}</loc>
    </url>
@endforeach
@foreach ($threads as $thread)
    <url>
        <loc>{{ route('threads.show', $thread) }}</loc>
@if ($thread->last_posted_at !== null)
        <lastmod>{{ $thread->last_posted_at->toAtomString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
