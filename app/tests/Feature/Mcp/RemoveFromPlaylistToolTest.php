<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\RemoveFromPlaylistTool;
use Illuminate\Support\Facades\Http;

it('removes an item from a playlist', function () {
    // Arrange
    Http::fake([
        '*/playlists/100/items/555' => Http::response(null, 200),
    ]);

    // Act
    $response = PlexServer::tool(RemoveFromPlaylistTool::class, [
        'playlist_id' => 100,
        'playlist_item_id' => 555,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('removed from playlist');
    $response->assertSee('"playlist_id":100');
    $response->assertSee('"playlist_item_id":555');
});

it('requires the playlist_id parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(RemoveFromPlaylistTool::class, [
        'playlist_item_id' => 555,
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('playlist_id parameter is required');
});

it('requires the playlist_item_id parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(RemoveFromPlaylistTool::class, [
        'playlist_id' => 100,
    ]);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('playlist_item_id parameter is required');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/playlists/100/items/555' => Http::response(['error' => 'Not found'], 404),
    ]);

    // Act
    $response = PlexServer::tool(RemoveFromPlaylistTool::class, [
        'playlist_id' => 100,
        'playlist_item_id' => 555,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('Failed to remove item');
});
