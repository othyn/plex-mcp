<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetWatchHistoryTool;
use Illuminate\Support\Facades\Http;

it('returns watch history', function () {
    // Arrange
    Http::fake([
        '*/status/sessions/history/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Inception',
                        'year' => 2010,
                        'viewedAt' => 1700000000,
                        'accountID' => 1,
                    ],
                    [
                        'type' => 'episode',
                        'title' => 'Pilot',
                        'grandparentTitle' => 'Breaking Bad',
                        'parentIndex' => 1,
                        'index' => 1,
                        'viewedAt' => 1699990000,
                        'accountID' => 1,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetWatchHistoryTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":2');
    $response->assertSee('Inception (2010) (Movie)');
    $response->assertSee('Breaking Bad - S1E1 - Pilot (TV Episode)');
    $response->assertSee('"viewed_at"');
});

it('returns empty results when no watch history exists', function () {
    // Arrange
    Http::fake([
        '*/status/sessions/history/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetWatchHistoryTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No watch history found');
    $response->assertSee('"total_results":0');
});

it('passes limit parameter to the API', function () {
    // Arrange
    Http::fake([
        '*/status/sessions/history/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    PlexServer::tool(GetWatchHistoryTool::class, [
        'limit' => 10,
    ]);

    // Assert
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'history/all')
            && $request['X-Plex-Container-Size'] === 10;
    });
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/status/sessions/history/all*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetWatchHistoryTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No watch history found');
});

it('includes music tracks in watch history', function () {
    // Arrange
    Http::fake([
        '*/status/sessions/history/all*' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'track',
                        'title' => 'Bohemian Rhapsody',
                        'grandparentTitle' => 'Queen',
                        'parentTitle' => 'A Night at the Opera',
                        'viewedAt' => 1700000000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetWatchHistoryTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('Queen - A Night at the Opera - Bohemian Rhapsody (Track)');
});
