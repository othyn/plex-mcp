# Plex MCP Server

A Model Context Protocol (MCP) server that bridges your Plex Media Server with AI assistants, enabling real-time contextual insights about your viewing experience.

## Motivation

I wanted to experiment with MCP and thought it would be interesting to integrate my Plex movie-watching experience with an LLM. This project provides real-time context of my current viewing experience, allowing me to:

- **Ask questions** about what's currently playing
- **Monitor sessions** across all devices
- **Deep-dive further** into cinematic elements while actively watching

<img src="./README/what-have-i-missed.png" width="300" /> <img src="./README/deep-dive.png" width="300" />

<img src="./README/lookup.png" width="300" /> <img src="./README/prompt.png" width="300" />

## Architecture

This server is built with **Laravel 12** and **Laravel MCP**, exposing functionality through both:

- **Streamable HTTP** - For web-based MCP clients
- **Stdio** - For local MCP client integration

### MCP Tools

#### Get Active Sessions

Monitors current playback sessions on your Plex server, including:

- User details and device information
- Playback progress and timestamps
- Transcoding status
- Media quality metrics

## Features

- **Real-time Session Monitoring** - Track active playback across all devices
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

**2. Ask about what's playing:**

```
User: Tell me more about this movie
Assistant: [Uses session info to identify the content and provide details]
```
