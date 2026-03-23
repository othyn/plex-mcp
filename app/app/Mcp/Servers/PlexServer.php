<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Plex\GetActiveSessionsTool;
use App\Mcp\Tools\Plex\SearchLibraryTool;
use Laravel\Mcp\Server;

final class PlexServer extends Server
{
    protected string $name = 'Plex Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Provides access to Plex Media Server. Use the available tools to monitor active playback sessions and search the library for movies and TV shows.';

    protected array $tools = [
        GetActiveSessionsTool::class,
        SearchLibraryTool::class,
    ];

    protected array $prompts = [];
}
