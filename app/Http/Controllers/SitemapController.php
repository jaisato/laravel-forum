<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Sitemap;
use Illuminate\Http\Response;

/**
 * /sitemap.xml (spec §4.2), built by the Sitemap service.
 */
final class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
