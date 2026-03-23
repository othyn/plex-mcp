<?php

declare(strict_types=1);

use App\Mcp\Servers\PlexServer;
use App\Mcp\Tools\Plex\GetContentDetailsTool;
use Illuminate\Support\Facades\Http;

it('returns movie details with genres directors and cast', function () {
    // Arrange
    Http::fake([
        '*/library/metadata/12345' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'movie',
                        'title' => 'Inception',
                        'year' => 2010,
                        'summary' => 'A thief who steals corporate secrets through dream-sharing technology.',
                        'rating' => 8.8,
                        'duration' => 8880000,
                        'studio' => 'Warner Bros.',
                        'contentRating' => 'PG-13',
                        'addedAt' => 1700000000,
                        'Genre' => [
                            ['tag' => 'Action'],
                            ['tag' => 'Sci-Fi'],
                            ['tag' => 'Thriller'],
                        ],
                        'Director' => [
                            ['tag' => 'Christopher Nolan'],
                        ],
                        'Role' => [
                            ['tag' => 'Leonardo DiCaprio', 'role' => 'Cobb'],
                            ['tag' => 'Joseph Gordon-Levitt', 'role' => 'Arthur'],
                        ],
                        'Media' => [
                            [
                                'videoResolution' => '1080p',
                                'bitrate' => 8000,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetContentDetailsTool::class, [
        'rating_key' => 12345,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('Inception (2010) (Movie)');
    $response->assertSee('"studio":"Warner Bros."');
    $response->assertSee('"content_rating":"PG-13"');
    $response->assertSee('"Action"');
    $response->assertSee('"Sci-Fi"');
    $response->assertSee('"Thriller"');
    $response->assertSee('Christopher Nolan');
    $response->assertSee('Leonardo DiCaprio');
    $response->assertSee('"role":"Cobb"');
    $response->assertSee('"resolution":"1080p"');
});

it('returns TV episode details', function () {
    // Arrange
    Http::fake([
        '*/library/metadata/67890' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'episode',
                        'title' => 'Pilot',
                        'grandparentTitle' => 'Breaking Bad',
                        'parentIndex' => 1,
                        'index' => 1,
                        'summary' => 'Walter White, a chemistry teacher, learns he has cancer.',
                        'duration' => 3480000,
                    ],
                ],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetContentDetailsTool::class, [
        'rating_key' => 67890,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('Breaking Bad - S1E1 - Pilot (TV Episode)');
    $response->assertSee('"show_title":"Breaking Bad"');
});

it('returns music track details', function () {
    // Arrange
    Http::fake([
        '*/library/metadata/11111' => Http::response([
            'MediaContainer' => [
                'Metadata' => [
                    [
                        'type' => 'track',
                        'title' => 'Bohemian Rhapsody',
                        'grandparentTitle' => 'Queen',
                        'parentTitle' => 'A Night at the Opera',
                        'index' => 11,
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
    $response = PlexServer::tool(GetContentDetailsTool::class, [
        'rating_key' => 11111,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"success"');
    $response->assertSee('Queen - A Night at the Opera - Bohemian Rhapsody (Track)');
    $response->assertSee('"artist":"Queen"');
    $response->assertSee('"album":"A Night at the Opera"');
    $response->assertSee('"track_number":11');
});

it('returns error for missing content', function () {
    // Arrange
    Http::fake([
        '*/library/metadata/99999' => Http::response([
            'MediaContainer' => [
                'Metadata' => [],
            ],
        ]),
    ]);

    // Act
    $response = PlexServer::tool(GetContentDetailsTool::class, [
        'rating_key' => 99999,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('No content found');
});

it('requires the rating_key parameter', function () {
    // Arrange
    Http::fake();

    // Act
    $response = PlexServer::tool(GetContentDetailsTool::class, []);

    // Assert
    $response->assertSee('"status":"error"');
    $response->assertSee('rating_key parameter is required');
});

it('handles API errors gracefully', function () {
    // Arrange
    Http::fake([
        '*/library/metadata/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    // Act
    $response = PlexServer::tool(GetContentDetailsTool::class, [
        'rating_key' => 12345,
    ]);

    // Assert
    $response->assertOk();
    $response->assertSee('"status":"error"');
    $response->assertSee('No content found');
});
