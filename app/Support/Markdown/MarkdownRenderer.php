<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use App\Models\User;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Turns member Markdown into HTML that is safe to print as is: raw HTML is
 * escaped, only http(s), mailto and relative links survive, external links
 * are marked as user generated, and mentions of real members link to their
 * profile. Posts store the result, so this runs once per write.
 */
#[Singleton]
final class MarkdownRenderer
{
    private const string EXTERNAL_REL = 'nofollow ugc noopener noreferrer';

    /** @var list<string> */
    private const array LINK_SCHEMES = ['http', 'https', 'mailto'];

    /** @var list<string> */
    private const array IMAGE_SCHEMES = ['http', 'https'];

    private readonly MarkdownParser $parser;

    private readonly HtmlRenderer $renderer;

    public function __construct(private readonly MentionParser $mentions)
    {
        $environment = MarkdownEnvironment::create();

        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
    }

    public function render(string $markdown): string
    {
        $document = $this->parser->parse($markdown);

        $this->resolveMentions($document);
        $this->secureLinks($document);

        return trim($this->renderer->renderDocument($document)->getContent());
    }

    /**
     * Plain text for listings, notifications and meta descriptions, cut at
     * a word boundary. Raw HTML is left out.
     */
    public function excerpt(string $markdown, int $length = 200): string
    {
        $text = '';

        foreach ($this->parser->parse($markdown)->iterator() as $node) {
            if ($node instanceof AbstractBlock || $node instanceof Newline) {
                $text .= ' ';
            }

            if ($node instanceof StringContainerInterface && ! $node instanceof HtmlBlock && ! $node instanceof HtmlInline) {
                $text .= $node->getLiteral();
            }
        }

        $text = Str::squish($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        // Prefer ending on a whole word when the cut fell inside one.
        if (mb_substr($text, $length, 1) !== ' ' && $lastSpace !== false) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, ' ,;:').'…';
    }

    /**
     * Links mentions of existing members (one query per document) and turns
     * every other mention back into plain text.
     */
    private function resolveMentions(Document $document): void
    {
        $usernames = $this->mentions->usernames($document);

        $existing = $usernames === []
            ? []
            : array_flip(User::query()->whereIn('username', $usernames)->pluck('username')->all());

        foreach ($this->nodes($document, Mention::class) as $mention) {
            $username = Str::lower($mention->getIdentifier());

            if (! $this->mentions->isLinkable($mention) || ! isset($existing[$username])) {
                $mention->replaceWith(new Text($mention->getPrefix().$mention->getIdentifier()));

                continue;
            }

            $mention->setUrl('/u/'.rawurlencode($username));
            $mention->setLabel('@'.$username);
            $mention->data->set('attributes/class', 'mention');
        }
    }

    /**
     * Allow-lists URL schemes (anything else keeps only its text) and tags
     * external links and images.
     */
    private function secureLinks(Document $document): void
    {
        foreach ($this->nodes($document, AbstractWebResource::class) as $node) {
            if ($node instanceof Mention) {
                continue;
            }

            $url = $node->getUrl();
            $scheme = $this->scheme($url);

            if ($node instanceof Image) {
                if (! in_array($scheme, self::IMAGE_SCHEMES, true)) {
                    $this->unwrap($node);

                    continue;
                }

                $node->data->set('attributes/loading', 'lazy');
                $node->data->set('attributes/referrerpolicy', 'no-referrer');

                continue;
            }

            if ($scheme !== null && ! in_array($scheme, self::LINK_SCHEMES, true)) {
                $this->unwrap($node);

                continue;
            }

            if ($this->isExternal($url, $scheme)) {
                $node->data->set('attributes/rel', self::EXTERNAL_REL);
                $node->data->set('attributes/target', '_blank');
            }
        }
    }

    /**
     * The URL scheme the way a browser could read it: browsers skip leading
     * control characters and spaces and drop tabs and newlines anywhere, so
     * "java\tscript:" still runs as JavaScript. CommonMark has already
     * percent-encoded those characters, hence the decoding first.
     */
    private function scheme(string $url): ?string
    {
        $normalized = (string) preg_replace('/[\x00-\x20\x7F]+/', '', rawurldecode($url));

        return preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches) === 1
            ? strtolower($matches[1])
            : null;
    }

    private function isExternal(string $url, ?string $scheme): bool
    {
        if (! in_array($scheme, ['http', 'https'], true) && ! str_starts_with($url, '//')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ! is_string($host) || ! is_string($appHost) || strcasecmp($host, $appHost) !== 0;
    }

    /**
     * Replaces a link or image with its own content (the link text or the
     * image description).
     */
    private function unwrap(Node $node): void
    {
        foreach ($node->children() as $child) {
            $node->insertBefore($child);
        }

        $node->detach();
    }

    /**
     * Collects the nodes first: the tree must not change while it is walked.
     *
     * @template TNode of Node
     *
     * @param  class-string<TNode>  $type
     * @return list<TNode>
     */
    private function nodes(Document $document, string $type): array
    {
        $nodes = [];

        foreach ($document->iterator() as $node) {
            if ($node instanceof $type) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }
}
