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
final class BrowseByGenreTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Browse content by genre within a library section. Lists available genres when no genre is specified, or returns matching content when a genre name is provided.';

    public function handle(Request $request): Response
    {
        try {
            $sectionKey = $request->get('section_key');

            if (! $sectionKey || ! is_numeric($sectionKey)) {
                return Response::text(json_encode([
                    'status' => 'error',
                    'message' => 'The section_key parameter is required. Use get-library-sections-tool to find section keys.',
                ]));
            }

            $sectionKey = (int) $sectionKey;
            $plexClient = new PlexClient;
            $genres = $plexClient->getGenresForSection($sectionKey);

            $genreName = $request->get('genre');

            if (! is_string($genreName) || mb_trim($genreName) === '') {
                return $this->listGenres($genres, $sectionKey);
            }

            return $this->browseByGenre($plexClient, $genres, $sectionKey, mb_trim($genreName), $request);
        } catch (Exception $exception) {
            Log::error('BrowseByGenreTool: Failed to browse by genre', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error browsing by genre: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'section_key' => $schema->integer()
                ->description('The library section key to browse genres for (use get-library-sections-tool to find section keys).')
                ->required(),

            'genre' => $schema->string()
                ->description('Genre name to filter by (e.g., "Action", "Comedy", "Rock"). If omitted, lists all available genres for the section.'),

            'limit' => $schema->integer()
                ->description('Maximum number of results to return when browsing by genre (1-100).'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $genres
     */
    private function listGenres(array $genres, int $sectionKey): Response
    {
        $genreList = array_map(
            fn (array $genre): array => [
                'key' => $genre['key'] ?? null,
                'title' => $genre['title'] ?? 'Unknown',
            ],
            $genres,
        );

        if ($genreList === []) {
            return Response::text(json_encode([
                'status' => 'success',
                'message' => sprintf('No genres found for section %d.', $sectionKey),
                'section_key' => $sectionKey,
                'total_results' => 0,
                'genres' => [],
            ]));
        }

        return Response::text(json_encode([
            'status' => 'success',
            'message' => sprintf('Found %d genres for section %d.', count($genreList), $sectionKey),
            'section_key' => $sectionKey,
            'total_results' => count($genreList),
            'genres' => $genreList,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $genres
     */
    private function browseByGenre(PlexClient $plexClient, array $genres, int $sectionKey, string $genreName, Request $request): Response
    {
        $matchedGenre = $this->resolveGenre($genres, $genreName);

        if ($matchedGenre === null) {
            $availableGenres = array_map(
                fn (array $genre): string => $genre['title'] ?? 'Unknown',
                $genres,
            );

            return Response::text(json_encode([
                'status' => 'error',
                'message' => sprintf('Genre "%s" not found. Available genres: %s', $genreName, implode(', ', $availableGenres)),
                'available_genres' => $availableGenres,
            ]));
        }

        $options = [];
        $limit = $request->get('limit');
        if (is_numeric($limit)) {
            $options['limit'] = (int) $limit;
        }

        $genreKey = (int) $matchedGenre['key'];
        $genreTitle = $matchedGenre['title'] ?? $genreName;

        Log::info('BrowseByGenreTool: Browsing by genre', [
            'sectionKey' => $sectionKey,
            'genre' => $genreTitle,
            'genreKey' => $genreKey,
        ]);

        $items = $plexClient->getItemsByGenre($sectionKey, $genreKey, $options);

        $results = array_map(
            $this->parseMetadata(...),
            $items,
        );

        if ($results === []) {
            return Response::text(json_encode([
                'status' => 'success',
                'message' => sprintf('No items found for genre "%s" in section %d.', $genreTitle, $sectionKey),
                'genre' => $genreTitle,
                'total_results' => 0,
                'results' => [],
            ]));
        }

        Log::info('BrowseByGenreTool: Browse completed', [
            'genre' => $genreTitle,
            'total_results' => count($results),
        ]);

        return Response::text(json_encode([
            'status' => 'success',
            'message' => sprintf('Found %d items for genre "%s".', count($results), $genreTitle),
            'genre' => $genreTitle,
            'total_results' => count($results),
            'results' => $results,
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $genres
     * @return array<string, mixed>|null
     */
    private function resolveGenre(array $genres, string $genreName): ?array
    {
        $normalizedInput = mb_strtolower($genreName);

        // Exact match (case-insensitive)
        foreach ($genres as $genre) {
            $title = $genre['title'] ?? '';
            if (mb_strtolower($title) === $normalizedInput) {
                return $genre;
            }
        }

        // Partial match fallback (case-insensitive)
        foreach ($genres as $genre) {
            $title = $genre['title'] ?? '';
            if (str_contains(mb_strtolower($title), $normalizedInput)) {
                return $genre;
            }
        }

        return null;
    }
}
