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
final class GetContentDetailsTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Get detailed information about a specific movie, TV show, episode, or music item, including genres, directors, and cast.';

    public function handle(Request $request): Response
    {
        try {
            $ratingKey = $request->get('rating_key');

            if (! is_numeric($ratingKey)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The rating_key parameter is required and must be a number.',
                ]));
            }

            $ratingKey = (int) $ratingKey;

            Log::info('GetContentDetailsTool: Getting details', ['ratingKey' => $ratingKey]);

            $plexClient = new PlexClient;
            $metadata = $plexClient->getMetadata($ratingKey);

            if ($metadata === []) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => sprintf('No content found with rating key %d.', $ratingKey),
                ]));
            }

            $details = $this->parseMetadata($metadata);

            if (isset($metadata['studio'])) {
                $details['studio'] = $metadata['studio'];
            }

            if (isset($metadata['contentRating'])) {
                $details['content_rating'] = $metadata['contentRating'];
            }

            if (isset($metadata['Genre']) && is_array($metadata['Genre'])) {
                $details['genres'] = array_map(
                    fn (array $genre): string => $genre['tag'] ?? 'Unknown',
                    $metadata['Genre'],
                );
            }

            if (isset($metadata['Director']) && is_array($metadata['Director'])) {
                $details['directors'] = array_map(
                    fn (array $director): string => $director['tag'] ?? 'Unknown',
                    $metadata['Director'],
                );
            }

            if (isset($metadata['Role']) && is_array($metadata['Role'])) {
                $details['cast'] = array_map(
                    fn (array $role): array => array_filter([
                        'name' => $role['tag'] ?? 'Unknown',
                        'role' => $role['role'] ?? null,
                        'thumb' => $role['thumb'] ?? null,
                    ]),
                    $metadata['Role'],
                );
            }

            if (isset($metadata['addedAt'])) {
                $details['added_at'] = date('Y-m-d H:i:s', (int) $metadata['addedAt']);
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Retrieved details for "%s"', $details['title']),
                'content' => $details,
            ];

            Log::info('GetContentDetailsTool: Details retrieved', [
                'ratingKey' => $ratingKey,
                'title' => $details['title'],
            ]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetContentDetailsTool: Failed to get details', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting content details: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'rating_key' => $schema->integer()
                ->description('The rating key (ID) of the content to get details for.')
                ->required(),
        ];
    }
}
