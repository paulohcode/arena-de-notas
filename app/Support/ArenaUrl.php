<?php

namespace App\Support;

class ArenaUrl
{
    public static function route(string $name, mixed $parameters = []): string
    {
        return route($name, $parameters, absolute: false);
    }

    public static function toLocalPath(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        $local = $path;
        if (is_string($query) && $query !== '') {
            $local .= '?'.$query;
        }
        if (is_string($fragment) && $fragment !== '') {
            $local .= '#'.$fragment;
        }

        return $local;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function localizePayload(array $payload): array
    {
        foreach (['url', 'accept_url', 'decline_url', 'show_url', 'redirect'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key])) {
                continue;
            }

            $local = self::toLocalPath($payload[$key]);
            if ($local !== null) {
                $payload[$key] = $local;
            }
        }

        return $payload;
    }
}
