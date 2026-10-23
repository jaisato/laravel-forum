<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\Generator\MentionGeneratorInterface;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Extension\Mention\MentionExtension;

/**
 * The forum's single CommonMark configuration. The renderer and the mention
 * parser share it, so both agree on where a mention can appear (never in
 * code) and the rendered HTML never contains user supplied markup.
 */
final class MarkdownEnvironment
{
    public static function create(): Environment
    {
        $environment = new Environment([
            // Raw HTML is shown as text; it is never interpreted.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            // Bounds for hostile input: deep nesting and delimiter runs are
            // the known ways to make CommonMark parsing slow.
            'max_nesting_level' => 20,
            'max_delimiters_per_line' => 1000,
            // Members expect Enter to start a new line, as on GitHub comments.
            'renderer' => ['soft_break' => "<br>\n"],
            'mentions' => [
                'user' => [
                    'prefix' => '@',
                    'pattern' => MentionParser::PATTERN,
                    // Every well-formed "@name" becomes a node; the renderer
                    // later keeps only those naming an existing member.
                    'generator' => new class implements MentionGeneratorInterface
                    {
                        public function generateMention(Mention $mention): Mention
                        {
                            return $mention;
                        }
                    },
                ],
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new MentionExtension);

        return $environment;
    }
}
