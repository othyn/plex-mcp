# Plex MCP Server

A Model Context Protocol (MCP) server that bridges your Plex Media Server with AI assistants, enabling real-time contextual insights about your viewing and listening experience.

## Motivation

I wanted to experiment with MCP and thought it would be interesting to integrate my Plex movie-watching experience with an LLM. This project provides real-time context of my current viewing experience, allowing me to:

- **Ask questions** about what's currently playing
- **Search your library** for movies, TV shows, and music
- **Monitor sessions** across all devices
- **Manage playlists** with full CRUD operations
- **Browse recently added** content
- **Deep-dive further** into cinematic elements while actively watching

<img src="./README/what-have-i-missed.png" width="300" /> <img src="./README/deep-dive.png" width="300" />

<img src="./README/lookup.png" width="300" /> <img src="./README/prompt.png" width="300" />

## Architecture

This server is built with **Laravel 12** and **Laravel MCP**, exposing functionality through both:

- **Streamable HTTP** - For web-based MCP clients
- **Stdio** - For local MCP client integration

### MCP Tools

| Tool | Description | Read-Only |
|------|-------------|-----------|
| **Get Active Sessions** | Monitor current playback sessions with user details, device info, transcoding status, and progress | Yes |
| **Search Library** | Search for movies, TV shows, and music by title or keyword with type filtering and result limits | Yes |
| **Get Content Details** | Get detailed information about a specific item including genres, directors, cast, and media quality | Yes |
| **Get Recently Added** | Browse recently added movies, TV shows, and music with configurable limits | Yes |
| **Get On Deck** | Get the "Continue Watching" queue with progress tracking | Yes |
| **Get Watch History** | View recently watched content sorted by most recent | Yes |
| **Get Library Sections** | List all library sections (Movies, TV Shows, Music, Photos) | Yes |
| **List Playlists** | List all playlists on the server (audio, video, and photo) | Yes |
| **Get Playlist Items** | Get the items in a specific playlist with item IDs for management | Yes |
| **Create Playlist** | Create a new playlist with specified content items | No |
| **Delete Playlist** | Delete a playlist from the server | No |
| **Add to Playlist** | Add content items to an existing playlist | No |
| **Remove from Playlist** | Remove a specific item from a playlist | No |

## Features

- **Real-time Session Monitoring** - Track active playback across all devices
- **Library Search** - Find movies, TV shows, and music in your Plex library
- **Content Details** - Get rich metadata including genres, directors, cast, and media quality
- **Continue Watching** - See your on deck queue with progress percentages
- **Watch History** - Review what's been watched and when
- **Library Overview** - See all library sections and their configurations
- **Playlist Management** - Full CRUD: create, delete, add to, remove from, and browse playlists
- **Recently Added** - Browse the latest additions to your library
- **Multi-client Support** - Works with any MCP-compatible client (Claude Desktop, Cursor, etc.)

## Setup

### Prerequisites

- Docker & Docker Compose

### 1. Get Your Plex Token

1. Open the **Plex Web App** in your browser and sign in
2. Start playing any media (or navigate to any library item)
3. Open your browser's **Developer Tools** (F12 or Cmd+Option+I on Mac)
4. Go to the **Network** tab
5. Look at any request going to your Plex server
6. Find the `X-Plex-Token` parameter in the request URL — that's your token

### 2. Configure Environment

```bash
cp docker/dev.env.example docker/dev.env
```

Edit `docker/dev.env` and set your Plex token:

```
PLEX_TOKEN=your-token-here
```

If your Plex server is not running on the same machine, also update `PLEX_URL`.

### 3. Start the Server

```bash
make start
```

This will build the Docker container, install dependencies, and start the server at `http://localhost:8001`.

Run `make help` to see all available commands.

## MCP Client Configuration

### HTTP Streaming

Add to your MCP client configuration:

```json
{
  "mcpServers": {
    "plex": {
      "type": "http",
      "url": "http://localhost:8001/mcp/plex"
    }
  }
}
```

### Stdio

```json
{
  "mcpServers": {
    "plex": {
      "command": "make",
      "args": ["mcp/plex"]
    }
  }
}
```

## Usage

### Example Workflows

**1. Monitor your viewing sessions:**

```
User: What am I currently watching?
Assistant: [Uses Get Active Sessions tool to show playback details]
```

**2. Search your library:**

```
User: Do I have any Christopher Nolan movies?
Assistant: [Uses Search Library tool to find matching movies]
```

**3. Search for music:**

```
User: Find Queen albums in my library
Assistant: [Uses Search Library tool with type=album to find matching albums]
```

**4. Get content details:**

```
User: Tell me more about Inception — who directed it and what genre is it?
Assistant: [Uses Get Content Details tool to show genres, directors, cast, and more]
```

**5. Manage playlists:**

```
User: Create a playlist called "Movie Night" with these movies
Assistant: [Uses Create Playlist tool to create a new video playlist]

User: What playlists do I have?
Assistant: [Uses List Playlists tool to show all playlists]

User: Add Interstellar to my Movie Night playlist
Assistant: [Uses Add to Playlist tool to add the item]
```

**6. Browse recently added:**

```
User: What's been added to my library recently?
Assistant: [Uses Get Recently Added tool to show the latest additions]
```

**7. Continue watching:**

```
User: What was I in the middle of watching?
Assistant: [Uses Get On Deck tool to show in-progress content with progress percentages]
```

**8. Watch history:**

```
User: What did I watch last week?
Assistant: [Uses Get Watch History tool to show recently watched content]
```

**9. Library overview:**

```
User: What libraries do I have set up?
Assistant: [Uses Get Library Sections tool to list all sections with types and paths]
```
