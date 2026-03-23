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
final class SearchLibraryTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Search the Plex library for movies, TV shows, and music by title or keyword.';

    public function handle(Request $request): Response
    {
        try {
            $query = $request->get('query');

            if (! $query || ! is_string($query) || mb_trim($query) === '') {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The query parameter is required.',
                ]));
            }

            $query = mb_trim($query);

            Log::info('SearchLibraryTool: Starting search', ['query' => $query]);

            $options = [];

            $type = $request->get('type');
            if (is_string($type) && in_array($type, ['movie', 'show', 'artist', 'album', 'track'], true)) {
                $options['type'] = $type;
            }

            $limit = $request->get('limit');
            if (is_numeric($limit)) {
                $options['limit'] = (int) $limit;
            }

            $plexClient = new PlexClient;
            $hubs = $plexClient->searchLibrary($query, $options);

            $results = [];
            foreach ($hubs as $hub) {
                $metadata = $hub['Metadata'] ?? [];
                foreach ($metadata as $item) {
                    $results[] = $this->parseMetadata($item);
                }
            }

            if ($results === []) {
                Log::info('SearchLibraryTool: No results found', ['query' => $query]);

                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => sprintf('No results found for "%s".', $query),
                    'query' => $query,
                    'total_results' => 0,
                    'results' => [],
                ]));
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d results for "%s"', count($results), $query),
                'query' => $query,
                'total_results' => count($results),
                'results' => $results,
            ];

            Log::info('SearchLibraryTool: Search completed', [
                'query' => $query,
                'total_results' => count($results),
            ]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('SearchLibraryTool: Search failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error searching library: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search term to find movies, TV shows, and music.')
                ->required(),

            'type' => $schema->string()
                ->enum(['movie', 'show', 'artist', 'album', 'track'])
                ->description('Filter results by content type.'),

            'limit' => $schema->integer()
                ->description('Maximum number of results to return per type (1-50).'),
        ];
    }
}
