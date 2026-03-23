<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex;

use App\Mcp\Tools\Plex\Concerns\ParsesPlexMetadata;
use App\Services\PlexClient;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

#[IsReadOnly]
final class GetOnDeckTool extends Tool
{
    use ParsesPlexMetadata;

    protected string $description = 'Get the "On Deck" / "Continue Watching" queue from Plex, showing in-progress movies and TV episodes.';

    public function handle(): Response
    {
        try {
            Log::info('GetOnDeckTool: Getting on deck content');

            $plexClient = new PlexClient;
            $items = $plexClient->getOnDeck();

            if ($items === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => 'No on deck content found.',
                    'total_results' => 0,
                    'results' => [],
                ]));
            }

            $results = [];
            foreach ($items as $item) {
                $parsed = $this->parseMetadata($item);

                if (isset($item['viewOffset'], $item['duration']) && $item['duration'] > 0) {
                    $progress = ($item['viewOffset'] / $item['duration']) * 100;
                    $parsed['progress_percent'] = round($progress, 1);
                    $parsed['minutes_remaining'] = max(0, (int) (($item['duration'] - $item['viewOffset']) / 1000 / 60));
                }

                if (isset($item['lastViewedAt'])) {
                    $parsed['last_viewed_at'] = date('Y-m-d H:i:s', (int) $item['lastViewedAt']);
                }

                $results[] = $parsed;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d items on deck', count($results)),
                'total_results' => count($results),
                'results' => $results,
            ];

            Log::info('GetOnDeckTool: Completed', ['total_results' => count($results)]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetOnDeckTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting on deck content: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
