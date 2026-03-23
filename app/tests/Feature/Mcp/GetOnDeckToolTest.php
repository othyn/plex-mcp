<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetOnDeckTool;
use Illuminate\Support\Facades\Http;

it('returns on deck content with progress', function () {
    // Arrange
    Http::fake([
        '*/library/onDeck' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Inception',
                        'year' => 2010,
                        'duration' => 8880000,
                        'viewOffset' => 4440000,
                        'lastViewedAt' => 1700000000,
                        'Media' => [
                            [
                                'videoResolution' => '1080p',
                                'bitrate' => 8000,
                            ],
                        ],
                    ],
                    [
                        'type' => 'episode',
                        'title' => 'Ozymandias',
                        'grandparentTitle' => 'Breaking Bad',
                        'parentIndex' => 5,
                        'index' => 14,
                        'duration' => 2820000,
                        'viewOffset' => 600000,
                        'lastViewedAt' => 1699990000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetOnDeckTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":2');
    $response->assertSee('Inception (2010) (Movie)');
    $response->assertSee('"progress_percent":50');
    $response->assertSee('Breaking Bad - S5E14 - Ozymandias (TV Episode)');
    $response->assertSee('"last_viewed_at"');
});

it('returns empty results when nothing is on deck', function () {
    // Arrange
    Http::fake([
        '*/library/onDeck' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetOnDeckTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No on deck content found');
    $response->assertSee('"total_results":0');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/library/onDeck' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetOnDeckTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No on deck content found');
});

it('calculates minutes remaining correctly', function () {
    // Arrange
    Http::fake([
        '*/library/onDeck' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Test Movie',
                        'duration' => 7200000,
                        'viewOffset' => 3600000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetOnDeckTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"minutes_remaining":60');
});
