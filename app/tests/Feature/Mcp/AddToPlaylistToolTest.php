<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\AddToPlaylistTool;
use Illuminate\Support\Facades\Http;

it('adds items to a playlist', function () {
    // Arrange
    Http::fake([
        '*/' => Http::response([
            'MediaContainer' => [
                'machineIdentifier' => 'abc123',
            ],
        ]),
        '*/playlists/100/items*' => Http::response(null, 200),
    ]);

    // Act
    $response = PlexServer::tool(AddToPlaylistTool::class, [
        'playlist_id' => 100,
        'rating_keys' => '12345,67890',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('added to playlist');
    $response->assertSee('"playlist_id":100');
});

it('requires the playlist_id parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(AddToPlaylistTool::class, [
        'rating_keys' => '12345',
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('playlist_id parameter is required');
});

it('requires the rating_keys parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(AddToPlaylistTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('rating_keys parameter is required');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/' => Http::response([
            'MediaContainer' => [
                'machineIdentifier' => 'abc123',
            ],
        ]),
        '*/playlists/100/items*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(AddToPlaylistTool::class, [
        'playlist_id' => 100,
        'rating_keys' => '12345',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('Failed to add items');
});
