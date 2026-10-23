<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resources that embed others (the author of a post, the participants of a
 * conversation…) return them as resources from toArray(), so the API
 * documentation describes them by their own schema. A JSON response would
 * resolve them on the way out anyway; resolving them here as well hands the
 * web's Inertia props plain arrays, the same as the API sends.
 */
trait ResolvesNestedResources
{
    /**
     * @return array<array-key, mixed>
     */
    public function resolve($request = null)
    {
        return self::resolveNested(parent::resolve($request), $request);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function resolveNested(array $data, mixed $request): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof JsonResource) {
                $data[$key] = $value->resolve($request);
            } elseif (is_array($value)) {
                $data[$key] = self::resolveNested($value, $request);
            }
        }

        return $data;
    }
}
