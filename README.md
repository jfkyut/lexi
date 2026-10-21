# Lexi

Lexi is an AI chat application for asking questions about legislation and legal
documents. It can search a connected legislative records database, analyze
supported attachments, and create downloadable documents.

## Features

- Authenticated conversations with streamed assistant responses.
- Legislative-record lookup through a connected, read-only MariaDB database.
- Attachment support for images, PDFs, DOCX, and text files.
- Downloadable PDF, DOCX, and Excel workbook generation when requested.
- Conversation and attachment storage in the application database.

Legislative search returns records and metadata, not the complete text of laws.
The assistant should not be treated as a substitute for legal advice or as a
source of unverified legal provisions.

## Requirements

- PHP 8.3 or later with the PHP extensions required by Laravel and this
  project, including ZIP and a database driver.
- Composer.
- Node.js and npm.
- SQLite for the default application database, or another database configured
  for Laravel.
- Access to an OpenAI-compatible chat-completions API.
- Access to the legacy MariaDB database for legislative search.

## Setup

Install the application dependencies and create the local environment file:

```sh
composer install
cp .env.example .env
php artisan key:generate
npm install
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

Configure the environment variables described below, then run the migrations
and build the frontend:

```sh
php artisan migrate
npm run build
```

The default application database is SQLite at `database/database.sqlite`.
Create that file if it does not already exist, or configure the `DB_*` values
to use another Laravel-supported database.

## Configuration

Set these values in `.env`:

| Variable | Purpose |
| --- | --- |
| `APP_NAME` | Application name shown by Laravel. |
| `APP_URL` | Base URL for the application. |
| `LLM_URL` | Full chat-completions endpoint URL, such as `https://api.example.com/v1/chat/completions`. |
| `LLM_API_KEY` | API key for the configured LLM provider. |
| `LLM_MODEL` | Model name supported by that provider. |
| `DB_CONNECTION` | Application database driver; defaults to `sqlite`. |
| `LEGACY_DB_HOST` | Host for the legislation MariaDB database. |
| `LEGACY_DB_PORT` | MariaDB port; defaults to `3306`. |
| `LEGACY_DB_DATABASE` | Legacy database name. |
| `LEGACY_DB_USERNAME` | Database account used for legislative lookups. |
| `LEGACY_DB_PASSWORD` | Password for that account. |

The `legacy` connection is separate from the application database. Use a
read-only database account for it. Legislative search expects the existing
legacy schema, including its documents, statuses, types, authors, sponsors,
committees, and council records. Without a working LLM configuration, chat
responses cannot be generated; without the legacy connection, legislative
lookup is unavailable.

Do not commit `.env` or put API keys and database credentials in source
control.

## Run locally

Start the development environment:

```sh
composer run dev
```

This runs the project's Laravel development command, including the application
and frontend development processes. Open the local URL shown in the command
output.

## Development checks

Run the PHP test suite:

```sh
php artisan test
```

Check the frontend and TypeScript:

```sh
npm run check
npm run types:check
```

Run the project-wide checks, including formatting, static analysis, frontend
checks, and tests:

```sh
composer ci:check
```

Build production assets with `npm run build`.

## Project layout

- `app/Http/Controllers` — chat, attachment, and account request handling.
- `app/Http/Services` — LLM integration, legislative lookup, attachment
  analysis, and generated-file handling.
- `resources/js` — Vue and Inertia frontend.
- `routes` — web and account settings routes.
- `database/migrations` — application database schema.
- `tests/Feature` — application and integration feature tests.
