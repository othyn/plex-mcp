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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

#[IsReadOnly]
final class SearchLibraryTool extends Tool
{
    protected string $description = 'Search the Plex library for movies and TV shows by title or keyword.';

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
            if (is_string($type) && in_array($type, ['movie', 'show'], true)) {
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
                ->description('The search term to find movies and TV shows.')
                ->required(),

            'type' => $schema->string()
                ->enum(['movie', 'show'])
                ->description('Filter results by content type.'),

            'limit' => $schema->integer()
                ->description('Maximum number of results to return per type (1-50).'),
        ];
    }

    private function parseMetadata(array $metadata): array
    {
        $type = $metadata['type'] ?? 'unknown';
        $title = $metadata['title'] ?? 'Unknown';

        $parsed = [
            'title' => $title,
            'type' => $type,
        ];

        if ($type === 'episode') {
            $showTitle = $metadata['grandparentTitle'] ?? 'Unknown Show';
            $seasonNumber = $metadata['parentIndex'] ?? '?';
            $episodeNumber = $metadata['index'] ?? '?';
            $parsed['content_description'] = sprintf(
                '%s - S%sE%s - %s (TV Episode)',
                $showTitle,
                $seasonNumber,
                $episodeNumber,
                $title,
            );
            $parsed['show_title'] = $showTitle;
            $parsed['season'] = $seasonNumber;
            $parsed['episode'] = $episodeNumber;
        } elseif ($type === 'show') {
            $year = $metadata['year'] ?? null;
            $parsed['content_description'] = $year
                ? sprintf('%s (%s) (TV Show)', $title, $year)
                : sprintf('%s (TV Show)', $title);
            $parsed['year'] = $year;
        } elseif ($type === 'movie') {
            $year = $metadata['year'] ?? null;
            $parsed['content_description'] = $year
                ? sprintf('%s (%s) (Movie)', $title, $year)
                : sprintf('%s (Movie)', $title);
            $parsed['year'] = $year;
        } else {
            $parsed['content_description'] = sprintf('%s (%s)', $title, $type);
        }

        if (isset($metadata['summary']) && $metadata['summary'] !== '') {
            $parsed['summary'] = $metadata['summary'];
        }

        if (isset($metadata['rating'])) {
            $parsed['rating'] = $metadata['rating'];
        }

        if (isset($metadata['duration']) && $metadata['duration'] > 0) {
            $parsed['duration_minutes'] = (int) ($metadata['duration'] / 1000 / 60);
        }

        $media = $metadata['Media'][0] ?? null;
        if ($media) {
            $mediaInfo = [];
            if (isset($media['videoResolution'])) {
                $mediaInfo['resolution'] = $media['videoResolution'];
            }
            if (isset($media['bitrate'])) {
                $mediaInfo['bitrate_kbps'] = $media['bitrate'];
            }
            if ($mediaInfo !== []) {
                $parsed['media_quality'] = $mediaInfo;
            }
        }

        return $parsed;
    }
}
