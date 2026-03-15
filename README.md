# onbarber-api

REST API for the Onbarber appointment system, built with Laravel 12.

## Requirements

- PHP >= 8.2
- Composer
- SQLite (default) or MySQL

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The server runs at `http://localhost:8000`.

Edit `.env` with your values — the only required ones beyond defaults:

```
BARBMAN_TOKEN=your_token_here
CORS_ALLOWED_ORIGINS=http://localhost:4321
```

## Commands

| Command              | Description                  |
| -------------------- | ---------------------------- |
| `composer dev`       | Start the dev server         |
| `composer test`      | Run the test suite           |
| `composer setup`     | Install, configure, migrate  |

## Docker

```bash
docker build -t onbarber-api .
docker run -p 8000:8000 onbarber-api
```

## Stack

- Laravel 12
- SQLite by default (MySQL-ready)
- Dockerized with PHP-FPM + Nginx
