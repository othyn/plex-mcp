<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\BrowseByGenreTool;
use Illuminate\Support\Facades\Http;

it('lists available genres when no genre parameter is provided', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                    ['key' => '2', 'title' => 'Comedy'],
                    ['key' => '3', 'title' => 'Drama'],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":3');
    $response->assertSee('"title":"Action"');
    $response->assertSee('"title":"Comedy"');
    $response->assertSee('"title":"Drama"');
});

it('returns items filtered by exact genre name', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                    ['key' => '2', 'title' => 'Comedy'],
                ],
            ],
        ]),
        '*/library/sections/1/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Die Hard',
                        'year' => 1988,
                        'summary' => 'An NYPD officer tries to save his wife.',
                        'rating' => 8.2,
                        'duration' => 7920000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'Action',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"genre":"Action"');
    $response->assertSee('"total_results":1');
    $response->assertSee('Die Hard (1988) (Movie)');
});

it('matches genre names case-insensitively', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                ],
            ],
        ]),
        '*/library/sections/1/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Die Hard',
                        'year' => 1988,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'action',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"genre":"Action"');
    $response->assertSee('Die Hard');
});

it('returns error with available genres when genre is not found', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                    ['key' => '2', 'title' => 'Comedy'],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'Horror',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('not found. Available genres');
    $response->assertSee('"available_genres"');
    $response->assertSee('Action');
    $response->assertSee('Comedy');
});

it('passes limit parameter to the API', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                ],
            ],
        ]),
        '*/library/sections/1/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'Action',
        'limit' => 10,
    ]);

    // Assert
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'library/sections/1/all')
            && $request['genre'] === 1
            && $request['X-Plex-Container-Size'] === 10
            && $request['X-Plex-Container-Start'] === 0;
    });
});

it('returns empty results when genre has no items', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Action'],
                ],
            ],
        ]),
        '*/library/sections/1/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'Action',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":0');
    $response->assertSee('No items found for genre');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":0');
    $response->assertSee('No genres found');
});

it('requires the section_key parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, []);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('section_key parameter is required');
});

it('supports partial genre name matching as fallback', function () {
    // Arrange
    Http::fake([
        '*/library/sections/1/genre*' => Http::response([
            'MediaContainer' => [
                'Directory' => [
                    ['key' => '1', 'title' => 'Science Fiction'],
                    ['key' => '2', 'title' => 'Comedy'],
                ],
            ],
        ]),
        '*/library/sections/1/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Blade Runner',
                        'year' => 1982,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(BrowseByGenreTool::class, [
        'section_key' => 1,
        'genre' => 'sci',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"genre":"Science Fiction"');
    $response->assertSee('Blade Runner');
});
