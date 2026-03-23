<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\SearchLibraryTool;
use Illuminate\Support\Facades\Http;

it('returns movie search results with metadata', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [
                    [
                        'type' => 'movie',
                        'title' => 'Movies',
                        'Metadata' => [
                            [
                                'type' => 'movie',
                                'title' => 'Inception',
                                'year' => 2010,
                                'summary' => 'A thief who steals corporate secrets through dream-sharing technology.',
                                'rating' => 8.8,
                                'duration' => 8880000,
                                'Media' => [
                                    [
                                        'videoResolution' => '1080p',
                                        'bitrate' => 8000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'Inception',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":1');
    $response->assertSee('Inception (2010) (Movie)');
    $response->assertSee('dream-sharing technology');
    $response->assertSee('"rating":8.8');
    $response->assertSee('"duration_minutes":148');
    $response->assertSee('"resolution":"1080p"');
});

it('returns TV show search results', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [
                    [
                        'type' => 'show',
                        'title' => 'Shows',
                        'Metadata' => [
                            [
                                'type' => 'show',
                                'title' => 'Breaking Bad',
                                'year' => 2008,
                                'summary' => 'A chemistry teacher diagnosed with cancer turns to manufacturing methamphetamine.',
                                'rating' => 9.5,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'Breaking Bad',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('Breaking Bad (2008) (TV Show)');
    $response->assertSee('"rating":9.5');
});

it('formats episodes with show name and season and episode numbers', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [
                    [
                        'type' => 'episode',
                        'title' => 'Episodes',
                        'Metadata' => [
                            [
                                'type' => 'episode',
                                'title' => 'Pilot',
                                'grandparentTitle' => 'Breaking Bad',
                                'parentIndex' => 1,
                                'index' => 1,
                                'duration' => 3480000,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'Pilot',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('Breaking Bad - S1E1 - Pilot (TV Episode)');
    $response->assertSee('"show_title":"Breaking Bad"');
    $response->assertSee('"season":1');
    $response->assertSee('"episode":1');
});

it('returns empty results when query matches nothing', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'nonexistentmovie12345',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No results found for');
    $response->assertSee('"total_results":0');
});

it('filters results by type when type parameter is provided', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [
                    [
                        'type' => 'movie',
                        'title' => 'Movies',
                        'Metadata' => [
                            [
                                'type' => 'movie',
                                'title' => 'The Dark Knight',
                                'year' => 2008,
                            ],
                        ],
                    ],
                    [
                        'type' => 'show',
                        'title' => 'Shows',
                        'Metadata' => [
                            [
                                'type' => 'show',
                                'title' => 'Dark',
                                'year' => 2017,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'dark',
        'type' => 'movie',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"total_results":1');
    $response->assertSee('The Dark Knight');
    $response->assertDontSee('"title":"Dark"');
});

it('passes limit parameter to the API', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [],
            ],
        ]),
    ]);

    // Act
    PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'test',
        'limit' => 5,
    ]);

    // Assert
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'hubs/search')
            && $request['query'] === 'test'
            && $request['limit'] === 5;
    });
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'test',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No results found');
});

it('handles missing optional metadata fields gracefully', function () {
    // Arrange
    Http::fake([
        '*/hubs/search*' => Http::response([
            'MediaContainer' => [
                'Hub' => [
                    [
                        'type' => 'movie',
                        'title' => 'Movies',
                        'Metadata' => [
                            [
                                'type' => 'movie',
                                'title' => 'Minimal Movie',
                                'Media' => [[]],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, [
        'query' => 'Minimal',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":1');
    $response->assertSee('Minimal Movie');
    $response->assertSee('Minimal Movie (Movie)');
});

it('requires the query parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(SearchLibraryTool::class, []);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('query parameter is required');
});
