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

final class DeletePlaylistTool extends Tool
{
    protected string $description = 'Delete a playlist from the Plex server.';

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

            Log::info('DeletePlaylistTool: Deleting playlist', ['playlistId' => $playlistId]);

            $plexClient = new PlexClient;
            $deleted = $plexClient->deletePlaylist($playlistId);

            if (! $deleted) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => sprintf('Failed to delete playlist %d.', $playlistId),
                ]));
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Playlist %d deleted successfully', $playlistId),
                'playlist_id' => $playlistId,
            ];

            Log::info('DeletePlaylistTool: Playlist deleted', ['playlistId' => $playlistId]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('DeletePlaylistTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error deleting playlist: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'playlist_id' => $schema->integer()
                ->description('The rating key (ID) of the playlist to delete.')
                ->required(),
        ];
    }
}
