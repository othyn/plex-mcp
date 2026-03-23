<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class PlexClient
{
    private string $baseUrl;

    private string $token;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('plex.url');
        $this->token = config('plex.token');
        $this->timeout = config('plex.timeout', 30);
    }

    /**
     * @param  array{type?: string, limit?: int}  $options
     * @return array<int, array<string, mixed>>
     */
    public function searchLibrary(string $query, array $options = []): array
    {
        try {
            Log::debug('Searching Plex library', ['query' => $query, 'options' => $options]);

            $params = ['query' => $query];

            if (isset($options['limit'])) {
                $params['limit'] = $options['limit'];
            }

            $data = $this->get('/hubs/search', $params);
            $hubs = $data['MediaContainer']['Hub'] ?? [];

            if (isset($options['type'])) {
                $hubs = array_values(array_filter(
                    $hubs,
                    fn (array $hub): bool => ($hub['type'] ?? '') === $options['type'],
                ));
            }

            Log::info('Plex library search completed', [
                'query' => $query,
                'hub_count' => count($hubs),
            ]);

            return $hubs;
        } catch (Exception $exception) {
            Log::error('Failed to search Plex library', [
                'query' => $query,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function getActiveSessions(): array
    {
        try {
            Log::debug('Getting active Plex sessions');

            $data = $this->get('/status/sessions');
            $sessions = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved active Plex sessions', [
                'session_count' => count($sessions),
            ]);

            return $sessions;
        } catch (Exception $exception) {
            Log::error('Failed to get active Plex sessions', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function get(string $endpoint, array $params = [], array $headers = []): array
    {
        $url = $this->buildUrl($endpoint);

        Log::debug('Plex API GET request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'params' => $params,
            'headers' => $headers,
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders(array_merge([
                'X-Plex-Token' => $this->token,
                'Accept' => 'application/json',
            ], $headers))
            ->get($url, $params);

        if ($response->failed()) {
            Log::error('Plex API GET request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception("Plex API request failed: {$response->status()} - {$response->body()}");
        }

        Log::info('Plex API GET request successful', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    private function buildUrl(string $endpoint): string
    {
        $endpoint = mb_ltrim($endpoint, '/');

        return "{$this->baseUrl}/{$endpoint}";
    }
}
