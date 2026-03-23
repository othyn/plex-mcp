<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetRecentlyAddedTool;
use Illuminate\Support\Facades\Http;

it('returns recently added content', function () {
    // Arrange
    Http::fake([
        '*/library/recentlyAdded*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Oppenheimer',
                        'year' => 2023,
                        'summary' => 'The story of the atomic bomb.',
                        'addedAt' => 1700000000,
                        'Media' => [
                            [
                                'videoResolution' => '4k',
                                'bitrate' => 20000,
                            ],
                        ],
                    ],
                    [
                        'type' => 'episode',
                        'title' => 'The Last One',
                        'grandparentTitle' => 'Friends',
                        'parentIndex' => 10,
                        'index' => 18,
                        'addedAt' => 1699999000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetRecentlyAddedTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":2');
    $response->assertSee('Oppenheimer (2023) (Movie)');
    $response->assertSee('Friends - S10E18 - The Last One (TV Episode)');
    $response->assertSee('"added_at"');
});

it('returns empty results when nothing recently added', function () {
    // Arrange
    Http::fake([
        '*/library/recentlyAdded*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetRecentlyAddedTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No recently added content found');
    $response->assertSee('"total_results":0');
});

it('passes limit parameter to the API', function () {
    // Arrange
    Http::fake([
        '*/library/recentlyAdded*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    PlexServer::tool(GetRecentlyAddedTool::class, [
        'limit' => 10,
    ]);

    // Assert
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'recentlyAdded')
            && $request['X-Plex-Container-Size'] === 10;
    });
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/library/recentlyAdded*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetRecentlyAddedTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No recently added content found');
});

it('includes music in recently added content', function () {
    // Arrange
    Http::fake([
        '*/library/recentlyAdded*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'album',
                        'title' => 'Midnights',
                        'parentTitle' => 'Taylor Swift',
                        'year' => 2022,
                        'addedAt' => 1700000000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetRecentlyAddedTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('Midnights by Taylor Swift (2022) (Album)');
    $response->assertSee('"artist":"Taylor Swift"');
});
