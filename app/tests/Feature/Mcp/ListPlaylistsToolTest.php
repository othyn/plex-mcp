<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\ListPlaylistsTool;
use Illuminate\Support\Facades\Http;

it('returns all playlists', function () {
    // Arrange
    Http::fake([
        '*/playlists' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'ratingKey' => '100',
                        'title' => 'My Movie Playlist',
                        'playlistType' => 'video',
                        'smart' => false,
                        'leafCount' => 5,
                        'duration' => 36000000,
                        'addedAt' => 1700000000,
                        'updatedAt' => 1700100000,
                    ],
                    [
                        'ratingKey' => '101',
                        'title' => 'Chill Music',
                        'playlistType' => 'audio',
                        'smart' => true,
                        'leafCount' => 25,
                        'duration' => 5400000,
                        'addedAt' => 1699000000,
                        'updatedAt' => 1699500000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(ListPlaylistsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":2');
    $response->assertSee('My Movie Playlist');
    $response->assertSee('"type":"video"');
    $response->assertSee('Chill Music');
    $response->assertSee('"type":"audio"');
    $response->assertSee('"item_count":5');
    $response->assertSee('"item_count":25');
});

it('returns empty results when no playlists exist', function () {
    // Arrange
    Http::fake([
        '*/playlists' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(ListPlaylistsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No playlists found');
    $response->assertSee('"total_results":0');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/playlists' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(ListPlaylistsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No playlists found');
});

it('identifies smart playlists', function () {
    // Arrange
    Http::fake([
        '*/playlists' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'ratingKey' => '200',
                        'title' => 'Smart Playlist',
                        'playlistType' => 'video',
                        'smart' => 1,
                        'leafCount' => 10,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(ListPlaylistsTool::class, []);

    // Assert
    $response->assertOk();
    $response->assertSee('"smart":true');
});
