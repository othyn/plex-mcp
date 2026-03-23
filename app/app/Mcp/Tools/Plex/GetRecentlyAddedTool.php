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
final class GetRecentlyAddedTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Get recently added movies, TV shows, and music from the Plex library.';

    public function handle(Request $request): Response
    {
        try {
            Log::info('GetRecentlyAddedTool: Getting recently added content');

            $options = [];

            $limit = $request->get('limit');
            if (is_numeric($limit)) {
                $options['limit'] = (int) $limit;
            }

            $plexClient = new PlexClient;
            $items = $plexClient->getRecentlyAdded($options);

            if ($items === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => 'No recently added content found.',
                    'total_results' => 0,
                    'results' => [],
                ]));
            }

            $results = [];
            foreach ($items as $item) {
                $parsed = $this->parseMetadata($item);

                if (isset($item['addedAt'])) {
                    $parsed['added_at'] = date('Y-m-d H:i:s', (int) $item['addedAt']);
                }

                $results[] = $parsed;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d recently added items', count($results)),
                'total_results' => count($results),
                'results' => $results,
            ];

            Log::info('GetRecentlyAddedTool: Completed', ['total_results' => count($results)]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetRecentlyAddedTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting recently added content: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of recently added items to return (default 50).'),
        ];
    }
}
