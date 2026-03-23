<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex;

use App\Services\PlexClient;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

#[IsReadOnly]
final class ListPlaylistsTool extends Tool
{
    protected string $description = 'List all playlists on the Plex server, including audio, video, and photo playlists.';

    public function handle(): Response
    {
        try {
            Log::info('ListPlaylistsTool: Getting playlists');

            $plexClient = new PlexClient;
            $playlists = $plexClient->getPlaylists();

            if ($playlists === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => 'No playlists found.',
                    'total_results' => 0,
                    'playlists' => [],
                ]));
            }

            $results = [];
            foreach ($playlists as $playlist) {
                $item = [
                    'rating_key' => $playlist['ratingKey'] ?? null,
                    'title' => $playlist['title'] ?? 'Unknown',
                    'type' => $playlist['playlistType'] ?? 'unknown',
                    'smart' => (bool) ($playlist['smart'] ?? false),
                    'item_count' => $playlist['leafCount'] ?? 0,
                ];

                if (isset($playlist['duration']) && $playlist['duration'] > 0) {
                    $item['duration_minutes'] = (int) ($playlist['duration'] / 1000 / 60);
                }

                if (isset($playlist['addedAt'])) {
                    $item['added_at'] = date('Y-m-d H:i:s', (int) $playlist['addedAt']);
                }

                if (isset($playlist['updatedAt'])) {
                    $item['updated_at'] = date('Y-m-d H:i:s', (int) $playlist['updatedAt']);
                }

                $results[] = $item;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d playlists', count($results)),
                'total_results' => count($results),
                'playlists' => $results,
            ];

            Log::info('ListPlaylistsTool: Completed', ['total_results' => count($results)]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('ListPlaylistsTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error listing playlists: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
