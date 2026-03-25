<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Plex\Concerns;

trait ParsesPlexMetadata
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function parseMetadata(array $metadata): array
    {
        $type = $metadata['type'] ?? 'unknown';
        $title = $metadata['title'] ?? 'Unknown';

        $parsed = [
            'rating_key' => $metadata['ratingKey'] ?? null,
            'title' => $title,
            'type' => $type,
        ];

        match ($type) {
            'episode' => $this->parseEpisodeMetadata($parsed, $metadata, $title),
            'show' => $this->parseShowMetadata($parsed, $metadata, $title),
            'movie' => $this->parseMovieMetadata($parsed, $metadata, $title),
            'artist' => $this->parseArtistMetadata($parsed, $metadata, $title),
            'album' => $this->parseAlbumMetadata($parsed, $metadata, $title),
            'track' => $this->parseTrackMetadata($parsed, $metadata, $title),
            default => $parsed['content_description'] = sprintf('%s (%s)', $title, $type),
        };

        if (isset($metadata['summary']) && $metadata['summary'] !== '') {
            $parsed['summary'] = $metadata['summary'];
        }

        if (isset($metadata['rating'])) {
            $parsed['rating'] = $metadata['rating'];
        }

        if (isset($metadata['duration']) && $metadata['duration'] > 0) {
            $parsed['duration_minutes'] = (int) ($metadata['duration'] / 1000 / 60);
        }

        $media = $metadata['Media'][0] ?? null;
        if ($media) {
            $parsed['media_quality'] = $this->parseMediaQuality($media, $type);
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseEpisodeMetadata(array &$parsed, array $metadata, string $title): void
    {
        $showTitle = $metadata['grandparentTitle'] ?? 'Unknown Show';
        $seasonNumber = $metadata['parentIndex'] ?? '?';
        $episodeNumber = $metadata['index'] ?? '?';
        $parsed['content_description'] = sprintf(
            '%s - S%sE%s - %s (TV Episode)',
            $showTitle,
            $seasonNumber,
            $episodeNumber,
            $title,
        );
        $parsed['show_title'] = $showTitle;
        $parsed['season'] = $seasonNumber;
        $parsed['episode'] = $episodeNumber;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseShowMetadata(array &$parsed, array $metadata, string $title): void
    {
        $year = $metadata['year'] ?? null;
        $parsed['content_description'] = $year
            ? sprintf('%s (%s) (TV Show)', $title, $year)
            : sprintf('%s (TV Show)', $title);
        $parsed['year'] = $year;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseMovieMetadata(array &$parsed, array $metadata, string $title): void
    {
        $year = $metadata['year'] ?? null;
        $parsed['content_description'] = $year
            ? sprintf('%s (%s) (Movie)', $title, $year)
            : sprintf('%s (Movie)', $title);
        $parsed['year'] = $year;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseArtistMetadata(array &$parsed, array $metadata, string $title): void
    {
        $parsed['content_description'] = sprintf('%s (Artist)', $title);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseAlbumMetadata(array &$parsed, array $metadata, string $title): void
    {
        $artist = $metadata['parentTitle'] ?? null;
        $year = $metadata['year'] ?? null;

        $description = $artist
            ? sprintf('%s by %s', $title, $artist)
            : $title;

        if ($year) {
            $description .= sprintf(' (%s)', $year);
        }

        $parsed['content_description'] = $description.' (Album)';
        $parsed['artist'] = $artist;
        $parsed['year'] = $year;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $metadata
     */
    private function parseTrackMetadata(array &$parsed, array $metadata, string $title): void
    {
        $artist = $metadata['grandparentTitle'] ?? 'Unknown Artist';
        $album = $metadata['parentTitle'] ?? 'Unknown Album';
        $parsed['content_description'] = sprintf(
            '%s - %s - %s (Track)',
            $artist,
            $album,
            $title,
        );
        $parsed['artist'] = $artist;
        $parsed['album'] = $album;

        if (isset($metadata['index'])) {
            $parsed['track_number'] = $metadata['index'];
        }
    }

    /**
     * @param  array<string, mixed>  $media
     * @return array<string, mixed>
     */
    private function parseMediaQuality(array $media, string $type): array
    {
        $mediaInfo = [];

        if (in_array($type, ['track', 'album', 'artist'], true)) {
            if (isset($media['bitrate'])) {
                $mediaInfo['bitrate_kbps'] = $media['bitrate'];
            }
            if (isset($media['audioCodec'])) {
                $mediaInfo['audio_codec'] = $media['audioCodec'];
            }
            if (isset($media['audioChannels'])) {
                $mediaInfo['audio_channels'] = $media['audioChannels'];
            }
        } else {
            if (isset($media['videoResolution'])) {
                $mediaInfo['resolution'] = $media['videoResolution'];
            }
            if (isset($media['bitrate'])) {
                $mediaInfo['bitrate_kbps'] = $media['bitrate'];
            }
        }

        return $mediaInfo;
    }
}
