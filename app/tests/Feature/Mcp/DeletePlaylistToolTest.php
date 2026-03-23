<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\DeletePlaylistTool;
use Illuminate\Support\Facades\Http;

it('deletes a playlist', function () {
    // Arrange
    Http::fake([
        '*/playlists/100' => Http::response(null, 200),
    ]);

    // Act
    $response = PlexServer::tool(DeletePlaylistTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('deleted successfully');
    $response->assertSee('"playlist_id":100');
});

it('requires the playlist_id parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(DeletePlaylistTool::class, []);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('playlist_id parameter is required');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/playlists/999' => Http::response(['error' => 'Not found'], 404),
    ]);

    // Act
    $response = PlexServer::tool(DeletePlaylistTool::class, [
        'playlist_id' => 999,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('Failed to delete playlist');
});
