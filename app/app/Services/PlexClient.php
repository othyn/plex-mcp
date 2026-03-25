<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PlexClient
{
    private readonly string $baseUrl;

    private readonly string $token;

    private readonly int $timeout;

    private static ?string $machineIdentifier = null;

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

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(int $ratingKey): array
    {
        try {
            Log::debug('Getting Plex metadata', ['ratingKey' => $ratingKey]);

            $data = $this->get("/library/metadata/{$ratingKey}");
            $metadata = $data['MediaContainer']['Metadata'][0] ?? [];

            Log::info('Retrieved Plex metadata', ['ratingKey' => $ratingKey]);

            return $metadata;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex metadata', [
                'ratingKey' => $ratingKey,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array{limit?: int}  $options
     * @return array<int, array<string, mixed>>
     */
    public function getRecentlyAdded(array $options = []): array
    {
        try {
            Log::debug('Getting recently added Plex content', ['options' => $options]);

            $params = [];
            if (isset($options['limit'])) {
                $params['X-Plex-Container-Size'] = $options['limit'];
                $params['X-Plex-Container-Start'] = 0;
            }

            $data = $this->get('/library/recentlyAdded', $params);
            $items = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved recently added Plex content', ['count' => count($items)]);

            return $items;
        } catch (Exception $exception) {
            Log::error('Failed to get recently added Plex content', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlaylists(): array
    {
        try {
            Log::debug('Getting Plex playlists');

            $data = $this->get('/playlists');
            $playlists = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved Plex playlists', ['count' => count($playlists)]);

            return $playlists;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex playlists', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlaylistItems(int $playlistId): array
    {
        try {
            Log::debug('Getting Plex playlist items', ['playlistId' => $playlistId]);

            $data = $this->get("/playlists/{$playlistId}/items");
            $items = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved Plex playlist items', [
                'playlistId' => $playlistId,
                'count' => count($items),
            ]);

            return $items;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex playlist items', [
                'playlistId' => $playlistId,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createPlaylist(string $title, string $type, string $uri): array
    {
        try {
            Log::debug('Creating Plex playlist', ['title' => $title, 'type' => $type]);

            $query = http_build_query([
                'title' => $title,
                'type' => $type,
                'smart' => 0,
                'uri' => $uri,
            ]);

            $data = $this->post("/playlists?{$query}");

            $playlist = $data['MediaContainer']['Metadata'][0] ?? [];

            Log::info('Created Plex playlist', ['title' => $title, 'type' => $type]);

            return $playlist;
        } catch (Exception $exception) {
            Log::error('Failed to create Plex playlist', [
                'title' => $title,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function deletePlaylist(int $playlistId): bool
    {
        try {
            Log::debug('Deleting Plex playlist', ['playlistId' => $playlistId]);

            $this->delete("/playlists/{$playlistId}");

            Log::info('Deleted Plex playlist', ['playlistId' => $playlistId]);

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to delete Plex playlist', [
                'playlistId' => $playlistId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function addToPlaylist(int $playlistId, string $uri): bool
    {
        try {
            Log::debug('Adding items to Plex playlist', ['playlistId' => $playlistId]);

            $query = http_build_query(['uri' => $uri]);

            $this->put("/playlists/{$playlistId}/items?{$query}");

            Log::info('Added items to Plex playlist', ['playlistId' => $playlistId]);

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to add items to Plex playlist', [
                'playlistId' => $playlistId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function getMachineIdentifier(): string
    {
        if (self::$machineIdentifier !== null) {
            return self::$machineIdentifier;
        }

        try {
            Log::debug('Getting Plex server machine identifier');

            $data = $this->get('/');
            self::$machineIdentifier = $data['MediaContainer']['machineIdentifier'] ?? '';

            Log::info('Retrieved Plex machine identifier');

            return self::$machineIdentifier;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex machine identifier', [
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOnDeck(): array
    {
        try {
            Log::debug('Getting Plex on deck content');

            $data = $this->get('/library/onDeck');
            $items = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved Plex on deck content', ['count' => count($items)]);

            return $items;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex on deck content', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLibrarySections(): array
    {
        try {
            Log::debug('Getting Plex library sections');

            $data = $this->get('/library/sections');
            $sections = $data['MediaContainer']['Directory'] ?? [];

            Log::info('Retrieved Plex library sections', ['count' => count($sections)]);

            return $sections;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex library sections', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array{limit?: int}  $options
     * @return array<int, array<string, mixed>>
     */
    public function getWatchHistory(array $options = []): array
    {
        try {
            Log::debug('Getting Plex watch history', ['options' => $options]);

            $params = ['sort' => 'viewedAt:desc'];

            if (isset($options['limit'])) {
                $params['X-Plex-Container-Size'] = $options['limit'];
                $params['X-Plex-Container-Start'] = 0;
            }

            $data = $this->get('/status/sessions/history/all', $params);
            $items = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved Plex watch history', ['count' => count($items)]);

            return $items;
        } catch (Exception $exception) {
            Log::error('Failed to get Plex watch history', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGenresForSection(int $sectionKey): array
    {
        try {
            Log::debug('Getting genres for Plex library section', ['sectionKey' => $sectionKey]);

            $data = $this->get("/library/sections/{$sectionKey}/genre");
            $genres = $data['MediaContainer']['Directory'] ?? [];

            Log::info('Retrieved genres for Plex library section', [
                'sectionKey' => $sectionKey,
                'count' => count($genres),
            ]);

            return $genres;
        } catch (Exception $exception) {
            Log::error('Failed to get genres for Plex library section', [
                'sectionKey' => $sectionKey,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array{limit?: int}  $options
     * @return array<int, array<string, mixed>>
     */
    public function getItemsByGenre(int $sectionKey, int $genreKey, array $options = []): array
    {
        try {
            Log::debug('Getting items by genre from Plex library section', [
                'sectionKey' => $sectionKey,
                'genreKey' => $genreKey,
                'options' => $options,
            ]);

            $params = ['genre' => $genreKey];

            if (isset($options['limit'])) {
                $params['X-Plex-Container-Size'] = $options['limit'];
                $params['X-Plex-Container-Start'] = 0;
            }

            $data = $this->get("/library/sections/{$sectionKey}/all", $params);
            $items = $data['MediaContainer']['Metadata'] ?? [];

            Log::info('Retrieved items by genre from Plex library section', [
                'sectionKey' => $sectionKey,
                'genreKey' => $genreKey,
                'count' => count($items),
            ]);

            return $items;
        } catch (Exception $exception) {
            Log::error('Failed to get items by genre from Plex library section', [
                'sectionKey' => $sectionKey,
                'genreKey' => $genreKey,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function removeFromPlaylist(int $playlistId, int $playlistItemId): bool
    {
        try {
            Log::debug('Removing item from Plex playlist', [
                'playlistId' => $playlistId,
                'playlistItemId' => $playlistItemId,
            ]);

            $this->delete("/playlists/{$playlistId}/items/{$playlistItemId}");

            Log::info('Removed item from Plex playlist', [
                'playlistId' => $playlistId,
                'playlistItemId' => $playlistItemId,
            ]);

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to remove item from Plex playlist', [
                'playlistId' => $playlistId,
                'playlistItemId' => $playlistItemId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildPlaylistUri(string $ratingKeys): string
    {
        $machineIdentifier = $this->getMachineIdentifier();

        return "server://{$machineIdentifier}/com.plexapp.plugins.library/library/metadata/{$ratingKeys}";
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

    private function post(string $endpoint, array $params = []): array
    {
        $url = $this->buildUrl($endpoint);

        Log::debug('Plex API POST request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'params' => $params,
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-Plex-Token' => $this->token,
                'Accept' => 'application/json',
            ])
            ->post($url, $params);

        if ($response->failed()) {
            Log::error('Plex API POST request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception("Plex API request failed: {$response->status()} - {$response->body()}");
        }

        Log::info('Plex API POST request successful', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    private function put(string $endpoint, array $params = []): array
    {
        $url = $this->buildUrl($endpoint);

        Log::debug('Plex API PUT request', [
            'endpoint' => $endpoint,
            'url' => $url,
            'params' => $params,
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-Plex-Token' => $this->token,
                'Accept' => 'application/json',
            ])
            ->put($url, $params);

        if ($response->failed()) {
            Log::error('Plex API PUT request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception("Plex API request failed: {$response->status()} - {$response->body()}");
        }

        Log::info('Plex API PUT request successful', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    private function delete(string $endpoint): void
    {
        $url = $this->buildUrl($endpoint);

        Log::debug('Plex API DELETE request', [
            'endpoint' => $endpoint,
            'url' => $url,
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-Plex-Token' => $this->token,
                'Accept' => 'application/json',
            ])
            ->delete($url);

        if ($response->failed()) {
            Log::error('Plex API DELETE request failed', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception("Plex API request failed: {$response->status()} - {$response->body()}");
        }

        Log::info('Plex API DELETE request successful', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
        ]);
    }

    private function buildUrl(string $endpoint): string
    {
        $endpoint = mb_ltrim($endpoint, '/');

        return "{$this->baseUrl}/{$endpoint}";
    }
}
