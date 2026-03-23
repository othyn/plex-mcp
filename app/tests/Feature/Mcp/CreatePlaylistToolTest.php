<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\CreatePlaylistTool;
use Illuminate\Support\Facades\Http;

it('creates a video playlist', function () {
    // Arrange
    Http::fake([
        '*/' => Http::response([
            'MediaContainer' => [
                'machineIdentifier' => 'abc123',
            ],
        ]),
        '*/playlists' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'ratingKey' => '300',
                        'title' => 'My New Playlist',
                        'playlistType' => 'video',
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'title' => 'My New Playlist',
        'type' => 'video',
        'rating_keys' => '12345,67890',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('My New Playlist');
    $response->assertSee('created successfully');
});

it('creates an audio playlist', function () {
    // Arrange
    Http::fake([
        '*/' => Http::response([
            'MediaContainer' => [
                'machineIdentifier' => 'abc123',
            ],
        ]),
        '*/playlists' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'ratingKey' => '301',
                        'title' => 'My Music',
                        'playlistType' => 'audio',
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'title' => 'My Music',
        'type' => 'audio',
        'rating_keys' => '11111',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('My Music');
});

it('requires the title parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'type' => 'video',
        'rating_keys' => '12345',
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('title parameter is required');
});

it('requires a valid type parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'title' => 'Test',
        'type' => 'invalid',
        'rating_keys' => '12345',
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('type parameter is required');
});

it('requires the rating_keys parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'title' => 'Test',
        'type' => 'video',
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
        '*/playlists' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(CreatePlaylistTool::class, [
        'title' => 'Test',
        'type' => 'video',
        'rating_keys' => '12345',
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('Failed to create playlist');
});
