<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex;

use App\Services\PlexClient;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Override;

#[IsReadOnly]
final class GetLibrarySectionsTool extends Tool
{
    protected string $description = 'List all library sections on the Plex server (e.g., Movies, TV Shows, Music, Photos).';

    public function handle(): Response
    {
        try {
            Log::info('GetLibrarySectionsTool: Getting library sections');

            $plexClient = new PlexClient;
            $sections = $plexClient->getLibrarySections();

            if ($sections === []) {
                return Response::text(json_encode([
                    'status' => 'success',
                    'message' => 'No library sections found.',
                    'total_results' => 0,
                    'sections' => [],
                ]));
            }

            $results = [];
            foreach ($sections as $section) {
                $item = [
                    'key' => $section['key'] ?? null,
                    'title' => $section['title'] ?? 'Unknown',
                    'type' => $section['type'] ?? 'unknown',
                    'agent' => $section['agent'] ?? null,
                    'scanner' => $section['scanner'] ?? null,
                    'language' => $section['language'] ?? null,
                ];

                if (isset($section['createdAt'])) {
                    $item['created_at'] = date('Y-m-d H:i:s', (int) $section['createdAt']);
                }

                if (isset($section['scannedAt'])) {
                    $item['last_scanned_at'] = date('Y-m-d H:i:s', (int) $section['scannedAt']);
                }

                if (isset($section['Location']) && is_array($section['Location'])) {
                    $item['locations'] = array_map(
                        fn (array $location): string => $location['path'] ?? 'Unknown',
                        $section['Location'],
                    );
                }

                $results[] = $item;
            }

            $result = [
                'status' => 'success',
                'message' => sprintf('Found %d library sections', count($results)),
                'total_results' => count($results),
                'sections' => $results,
            ];

            Log::info('GetLibrarySectionsTool: Completed', ['total_results' => count($results)]);

            return Response::text(json_encode($result));
        } catch (Exception $exception) {
            Log::error('GetLibrarySectionsTool: Failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Response::text(json_encode([
                'status' => 'error',
                'message' => 'Error getting library sections: '.$exception->getMessage(),
            ]));
        }
    }

    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
