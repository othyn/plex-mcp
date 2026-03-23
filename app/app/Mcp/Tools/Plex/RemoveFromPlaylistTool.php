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

final class RemoveFromPlaylistTool extends Tool
{
    protected string $description = 'Remove a specific item from a Plex playlist.';

    public function handle(Request $request): Response
    {
        try {
            $playlistId = $request->get('playlist_id');
            $playlistItemId = $request->get('playlist_item_id');

            if (! is_numeric($playlistId)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The playlist_id parameter is required and must be a number.',
                ]));
            }

            if (! is_numeric($playlistItemId)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The playlist_item_id parameter is required and must be a number.',
                ]));
            }

            $playlistId = (int) $playlistId;
            $playlistItemId = (int) $playlistItemId;

            Log::info('RemoveFromPlaylistTool: Removing item from playlist', [
                'playlistId' => $playlistId,
                'playlistItemId' => $playlistItemId,
            ]);

            $plexClient = new PlexClient;
            $removed = $plexClient->removeFromPlaylist($playlistId, $playlistItemId);

            if (! $removed) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => sprintf('Failed to remove item %d from playlist %d.', $playlistItemId, $playlistId),
                ]));
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Item %d removed from playlist %d successfully', $playlistItemId, $playlistId),
                'playlist_id' => $playlistId,
                'playlist_item_id' => $playlistItemId,
            ];

            Log::info('RemoveFromPlaylistTool: Item removed', [
                'playlistId' => $playlistId,
                'playlistItemId' => $playlistItemId,
            ]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('RemoveFromPlaylistTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error removing from playlist: '.$exception->getMessage(),
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

            'playlist_item_id' => $schema->integer()
                ->description('The playlist item ID of the item to remove (from Get Playlist Items response).')
                ->required(),
        ];
    }
}
