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

final class CreatePlaylistTool extends Tool
{
    protected string $description = 'Create a new playlist on the Plex server with the specified content items.';

    public function handle(Request $request): Response
    {
        try {
            $title = $request->get('title');
            $type = $request->get('type');
            $ratingKeys = $request->get('rating_keys');

            if (! $title || ! is_string($title) || mb_trim($title) === '') {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The title parameter is required.',
                ]));
            }

            if (! is_string($type) || ! in_array($type, ['audio', 'video', 'photo'], true)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The type parameter is required and must be one of: audio, video, photo.',
                ]));
            }

            if (! $ratingKeys || ! is_string($ratingKeys) || mb_trim($ratingKeys) === '') {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The rating_keys parameter is required (comma-separated rating keys).',
                ]));
            }

            $title = mb_trim($title);
            $ratingKeys = mb_trim($ratingKeys);

            Log::info('CreatePlaylistTool: Creating playlist', [
                'title' => $title,
                'type' => $type,
                'ratingKeys' => $ratingKeys,
            ]);

            $plexClient = new PlexClient;
            $uri = $plexClient->buildPlaylistUri($ratingKeys);
            $playlist = $plexClient->createPlaylist($title, $type, $uri);

            if ($playlist === []) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'Failed to create playlist.',
                ]));
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Playlist "%s" created successfully', $title),
                'playlist' => [
                    'rating_key' => $playlist['ratingKey'] ?? null,
                    'title' => $playlist['title'] ?? $title,
                    'type' => $playlist['playlistType'] ?? $type,
                ],
            ];

            Log::info('CreatePlaylistTool: Playlist created', ['title' => $title]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('CreatePlaylistTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error creating playlist: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The name for the new playlist.')
                ->required(),

            'type' => $schema->string()
                ->enum(['audio', 'video', 'photo'])
                ->description('The type of content in the playlist.')
                ->required(),

            'rating_keys' => $schema->string()
                ->description('Comma-separated rating keys (IDs) of content items to add to the playlist.')
                ->required(),
        ];
    }
}
