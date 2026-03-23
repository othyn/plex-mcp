<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Plex\GetActiveSessionsTool;
use Laravel\Mcp\Server;

final class PlexServer extends Server
{
    protected string $name = 'Plex Server';

    protected string $version = '1.0.0';

    protected string $instructions = 'Provides access to Plex Media Server sessions. Use the available tools to monitor active playback sessions.';

    protected array $tools = [
        GetActiveSessionsTool::class,
    ];

    protected array $prompts = [];
}
