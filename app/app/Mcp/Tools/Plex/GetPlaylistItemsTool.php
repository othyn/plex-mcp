<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex;

use App\Mcp\Tools\Plex\Concerns\ParsesPlexMetadata;
use App\Services\PlexClient;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

#[IsReadOnly]
final class GetPlaylistItemsTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Get the items in a specific Plex playlist.';

    public function handle(Request $request): Response
    {
        try {
            $playlistId = $request->get('playlist_id');

            if (! is_numeric($playlistId)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The playlist_id parameter is required and must be a number.',
                ]));
            }

            $playlistId = (int) $playlistId;

            Log::info('GetPlaylistItemsTool: Getting playlist items', ['playlistId' => $playlistId]);

            $plexClient = new PlexClient;
            $items = $plexClient->getPlaylistItems($playlistId);

            if ($items === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => sprintf('No items found in playlist %d.', $playlistId),
                    'playlist_id' => $playlistId,
                    'total_results' => 0,
                    'items' => [],
                ]));
            }

            $results = [];
            foreach ($items as $item) {
                $parsed = $this->parseMetadata($item);

                if (isset($item['playlistItemID'])) {
                    $parsed['playlist_item_id'] = $item['playlistItemID'];
                }

                if (isset($item['ratingKey'])) {
                    $parsed['rating_key'] = $item['ratingKey'];
                }

                $results[] = $parsed;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d items in playlist', count($results)),
                'playlist_id' => $playlistId,
                'total_results' => count($results),
                'items' => $results,
            ];

            Log::info('GetPlaylistItemsTool: Completed', [
                'playlistId' => $playlistId,
                'total_results' => count($results),
            ]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetPlaylistItemsTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting playlist items: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'playlist_id' => $schema->integer()
                ->description('The rating key (ID) of the playlist.')
                ->required(),
        ];
    }
}
