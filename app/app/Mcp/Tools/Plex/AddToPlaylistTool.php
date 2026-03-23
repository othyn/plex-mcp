<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex;

use App\Services\PlexClient;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Override;

final class AddToPlaylistTool extends Tool
{
    protected string $description = 'Add content items to an existing Plex playlist.';

    public function handle(Request $request): Response
    {
        try {
            $playlistId = $request->get('playlist_id');
            $ratingKeys = $request->get('rating_keys');

            if (! is_numeric($playlistId)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The playlist_id parameter is required and must be a number.',
                ]));
            }

            if (! $ratingKeys || ! is_string($ratingKeys) || mb_trim($ratingKeys) === '') {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The rating_keys parameter is required (comma-separated rating keys).',
                ]));
            }

            $playlistId = (int) $playlistId;
            $ratingKeys = mb_trim($ratingKeys);

            Log::info('AddToPlaylistTool: Adding items to playlist', [
                'playlistId' => $playlistId,
                'ratingKeys' => $ratingKeys,
            ]);

            $plexClient = new PlexClient;
            $uri = $plexClient->buildPlaylistUri($ratingKeys);
            $added = $plexClient->addToPlaylist($playlistId, $uri);

            if (! $added) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => sprintf('Failed to add items to playlist %d.', $playlistId),
                ]));
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Items added to playlist %d successfully', $playlistId),
                'playlist_id' => $playlistId,
                'rating_keys' => $ratingKeys,
            ];

            Log::info('AddToPlaylistTool: Items added', ['playlistId' => $playlistId]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('AddToPlaylistTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error adding to playlist: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'playlist_id' => $schema->integer()
                ->description('The rating key (ID) of the playlist to add items to.')
                ->required(),

            'rating_keys' => $schema->string()
                ->description('Comma-separated rating keys (IDs) of content items to add.')
                ->required(),
        ];
    }
}
