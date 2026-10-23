<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The editor's preview, rendered exactly as a published post would be.
 */
final class MarkdownPreviewController extends Controller
{
    public function __invoke(Request $request, MarkdownRenderer $markdown): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:'.(int) config('forum.posts.body_max')],
        ]);

        return response()->json(['html' => $markdown->render((string) ($validated['body'] ?? ''))]);
    }
}
