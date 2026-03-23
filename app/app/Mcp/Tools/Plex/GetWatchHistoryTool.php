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
final class GetWatchHistoryTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Get watch history from Plex, showing recently watched movies, TV episodes, and music tracks sorted by most recent.';

    public function handle(Request $request): Response
    {
        try {
            Log::info('GetWatchHistoryTool: Getting watch history');

            $options = [];

            $limit = $request->get('limit');
            if (is_numeric($limit)) {
                $options['limit'] = (int) $limit;
            }

            $plexClient = new PlexClient;
            $items = $plexClient->getWatchHistory($options);

            if ($items === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => 'No watch history found.',
                    'total_results' => 0,
                    'results' => [],
                ]));
            }

            $results = [];
            foreach ($items as $item) {
                $parsed = $this->parseMetadata($item);

                if (isset($item['viewedAt'])) {
                    $parsed['viewed_at'] = date('Y-m-d H:i:s', (int) $item['viewedAt']);
                }

                if (isset($item['accountID'])) {
                    $parsed['account_id'] = $item['accountID'];
                }

                $results[] = $parsed;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d items in watch history', count($results)),
                'total_results' => count($results),
                'results' => $results,
            ];

            Log::info('GetWatchHistoryTool: Completed', ['total_results' => count($results)]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetWatchHistoryTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting watch history: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of watch history items to return (default 50).'),
        ];
    }
}
