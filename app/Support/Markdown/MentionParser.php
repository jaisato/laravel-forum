<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Finds "@username" mentions with the real Markdown parser, so mentions in
 * code, inside links or in e-mail addresses are never picked up.
 */
#[Singleton]
final class MentionParser
{
    /**
     * What may follow the "@". Matching is case-insensitive; the length
     * rules of usernames are applied afterwards.
     */
    public const string PATTERN = '[A-Za-z0-9_-]+';

    private readonly MarkdownParser $parser;

    public function __construct()
    {
        $this->parser = new MarkdownParser(MarkdownEnvironment::create());
    }

    /**
     * @return list<string> lowercase usernames, without duplicates
     */
    public function extract(string $markdown): array
    {
        return $this->usernames($this->parser->parse($markdown));
    }

    /**
     * @return list<string> lowercase usernames, without duplicates
     */
    public function usernames(Document $document): array
    {
        $usernames = [];

        foreach ($document->iterator() as $node) {
            if ($node instanceof Mention && $this->isLinkable($node)) {
                $usernames[Str::lower($node->getIdentifier())] = true;
            }
        }

        return array_keys($usernames);
    }

    /**
     * Whether the mention may become a profile link: it has the shape of a
     * username and is not part of another link or of an image description.
     */
    public function isLinkable(Mention $mention): bool
    {
        $length = strlen($mention->getIdentifier());

        if ($length < 3 || $length > 30) {
            return false;
        }

        for ($parent = $mention->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof AbstractWebResource) {
                return false;
            }
        }

        return true;
    }
}
