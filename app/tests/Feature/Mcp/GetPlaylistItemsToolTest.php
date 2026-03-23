<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetPlaylistItemsTool;
use Illuminate\Support\Facades\Http;

it('returns playlist items', function () {
    // Arrange
    Http::fake([
        '*/playlists/100/items' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Inception',
                        'year' => 2010,
                        'Media' => [
                            [
                                'videoResolution' => '1080p',
                                'bitrate' => 8000,
                            ],
                        ],
                    ],
                    [
                        'type' => 'movie',
                        'title' => 'The Dark Knight',
                        'year' => 2008,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetPlaylistItemsTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('"total_results":2');
    $response->assertSee('Inception (2010) (Movie)');
    $response->assertSee('The Dark Knight (2008) (Movie)');
});

it('returns empty results for empty playlist', function () {
    // Arrange
    Http::fake([
        '*/playlists/100/items' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetPlaylistItemsTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No items found in playlist');
    $response->assertSee('"total_results":0');
});

it('returns music items from audio playlist', function () {
    // Arrange
    Http::fake([
        '*/playlists/200/items' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'track',
                        'title' => 'Bohemian Rhapsody',
                        'grandparentTitle' => 'Queen',
                        'parentTitle' => 'A Night at the Opera',
                        'duration' => 354000,
                        'Media' => [
                            [
                                'bitrate' => 320,
                                'audioCodec' => 'mp3',
                                'audioChannels' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetPlaylistItemsTool::class, [
        'playlist_id' => 200,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('Queen - A Night at the Opera - Bohemian Rhapsody (Track)');
});

it('requires the playlist_id parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(GetPlaylistItemsTool::class, []);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('playlist_id parameter is required');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/playlists/*/items' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetPlaylistItemsTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('No items found in playlist');
});
