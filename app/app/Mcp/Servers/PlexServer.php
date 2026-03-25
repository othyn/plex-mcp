<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Plex\AddToPlaylistTool;
use App\Mcp\Tools\Plex\BrowseByGenreTool;
use App\Mcp\Tools\Plex\CreatePlaylistTool;
use App\Mcp\Tools\Plex\DeletePlaylistTool;
use App\Mcp\Tools\Plex\GetActiveSessionsTool;
use App\Mcp\Tools\Plex\GetContentDetailsTool;
use App\Mcp\Tools\Plex\GetLibrarySectionsTool;
use App\Mcp\Tools\Plex\GetOnDeckTool;
use App\Mcp\Tools\Plex\GetPlaylistItemsTool;
use App\Mcp\Tools\Plex\GetRecentlyAddedTool;
use App\Mcp\Tools\Plex\GetWatchHistoryTool;
use App\Mcp\Tools\Plex\ListPlaylistsTool;
use App\Mcp\Tools\Plex\RemoveFromPlaylistTool;
use App\Mcp\Tools\Plex\SearchLibraryTool;
use Laravel\Mcp\Server;

final class PlexServer extends Server
{
    protected string $name = 'Plex Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Provides access to Plex Media Server. Use the available tools to monitor active playback sessions, search the library for movies, TV shows, and music, get content details, browse by genre, manage playlists, browse recently added content, view watch history, and check the on deck queue.';

    protected array $tools = [
        GetActiveSessionsTool::class,
        SearchLibraryTool::class,
        GetContentDetailsTool::class,
        BrowseByGenreTool::class,
        GetRecentlyAddedTool::class,
        GetOnDeckTool::class,
        GetWatchHistoryTool::class,
        GetLibrarySectionsTool::class,
        ListPlaylistsTool::class,
        GetPlaylistItemsTool::class,
        CreatePlaylistTool::class,
        DeletePlaylistTool::class,
        AddToPlaylistTool::class,
        RemoveFromPlaylistTool::class,
    ];

    protected array $prompts = [];
}
