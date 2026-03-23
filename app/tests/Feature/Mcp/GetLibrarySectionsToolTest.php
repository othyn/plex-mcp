<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetLibrarySectionsTool;
use Illuminate\Support\Facades\Http;

it('returns all library sections', function () {
    // Arrange
    Http::fake([
        '*/library/sections' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    [
                        'key' => '1',
                        'title' => 'Movies',
                        'type' => 'movie',
                        'agent' => 'tv.plex.agents.movie',
                        'scanner' => 'Plex Movie',
                        'language' => 'en-US',
                        'createdAt' => 1600000000,
                        'scannedAt' => 1700000000,
                        'Location' => [
                            ['path' => '/media/movies'],
                        ],
                    ],
                    [
                        'key' => '2',
                        'title' => 'TV Shows',
                        'type' => 'show',
                        'agent' => 'tv.plex.agents.series',
                        'scanner' => 'Plex TV Series',
                        'language' => 'en-US',
                        'createdAt' => 1600000000,
                        'scannedAt' => 1700000000,
                        'Location' => [
                            ['path' => '/media/tv'],
                        ],
                    ],
                    [
                        'key' => '3',
                        'title' => 'Music',
                        'type' => 'artist',
                        'agent' => 'tv.plex.agents.music',
                        'scanner' => 'Plex Music',
                        'language' => 'en-US',
                        'createdAt' => 1600000000,
                        'scannedAt' => 1700000000,
                        'Location' => [
                            ['path' => '/media/music'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetLibrarySectionsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":3');
    $response->assertSee('"title":"Movies"');
    $response->assertSee('"type":"movie"');
    $response->assertSee('"title":"TV Shows"');
    $response->assertSee('"type":"show"');
    $response->assertSee('"title":"Music"');
    $response->assertSee('"type":"artist"');
    $response->assertSee('locations');
});

it('returns empty results when no sections exist', function () {
    // Arrange
    Http::fake([
        '*/library/sections' => Http::response([
            'MediaContainer' => [
                'Directory' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetLibrarySectionsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No library sections found');
    $response->assertSee('"total_results":0');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/library/sections' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetLibrarySectionsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No library sections found');
});
