<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The address search engines should index a page under (SeoHead): the page
 * itself, with its page number and the query parameters that make it a
 * page of its own (a profile's replies tab), and without sorts, filters or
 * anything else in the query.
 */
final class CanonicalUrl
{
    /**
     * @param  array<string, string>  $query  what makes the page one of its own
     */
    public static function for(Request $request, array $query = []): string
    {
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);

        if ($page !== false) {
            $query['page'] = (string) $page;
        }

        return $request->url().($query === [] ? '' : '?'.http_build_query($query));
    }
}
