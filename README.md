# onbarber-api

![Hono](https://img.shields.io/badge/Hono-4-E36002?logo=hono&logoColor=white)
![Cloudflare Workers](https://img.shields.io/badge/Cloudflare-Workers-F38020?logo=cloudflare&logoColor=white)
![D1](https://img.shields.io/badge/D1-SQLite-F38020?logo=cloudflare&logoColor=white)
![License](https://img.shields.io/badge/license-Apache%202.0-blue)

REST API for **OnBarber**, an appointment booking system for a barbershop in Encarnación, Paraguay. It exposes public endpoints for clients to browse barbers and book time slots, and protected endpoints for the admin panel (Barbman) to manage appointments and schedules.

> **Frontend repository:** [onbarber-web](https://github.com/matisanabria/onbarber-web/) — built with Astro 6 + React 19 + Tailwind CSS 4.
>
> **Admin panel:** [barbman](https://github.com/matisanabria/barbman) — manages appointments and schedules via the protected endpoints.

Rewritten from a Laravel/MariaDB backend to run on Cloudflare Workers — the route/payload/status-code contract is unchanged, since a local ("barbman") system integrates directly against it.

---

## How it works

- Barbers have a **weekly schedule** (open/close times per day, optional break window).
- **Schedule overrides** let admins close a specific date or change hours for it.
- Clients query available **1-hour slots** for a given barber and date; the API excludes booked and break-time slots.
- Appointments are validated server-side before creation (active barber, open day, slot within hours, no conflicts).
- Two access tiers: **public** (rate-limited) and **admin** (Bearer token required).

---

## Tech stack

| Layer       | Technology                        |
|-------------|-----------------------------------|
| Runtime     | Cloudflare Workers                |
| Framework   | Hono 4                            |
| Language    | TypeScript                        |
| Database    | Cloudflare D1 (SQLite) via Drizzle ORM |
| Auth        | Static Bearer token (Barbman)     |
| Rate limiting | D1-backed sliding window (see below) |
| Hosting     | Cloudflare Workers                |

---

## Prerequisites

- Node.js >= 22.12.0
- [pnpm](https://pnpm.io/)
- A Cloudflare account (`wrangler login`)

---

## Installation

```bash
git clone https://github.com/matisanabria/onbarber-api.git
cd onbarber-api
pnpm install
```

---

## Environment setup

Config lives in `wrangler.jsonc` (D1 binding, `CORS_ALLOWED_ORIGINS`) plus one secret.

**Local dev** — create `.dev.vars` (gitignored):

```env
BARBMAN_TOKEN=dev-secret-token
```

**Production** — set via CLI, never in a file:

```bash
wrangler secret put BARBMAN_TOKEN
```

| Variable               | Where                  | Description                                     |
|------------------------|-------------------------|--------------------------------------------------|
| `BARBMAN_TOKEN`        | secret (`.dev.vars` / `wrangler secret`) | Bearer token for all admin/protected endpoints |
| `CORS_ALLOWED_ORIGINS` | `wrangler.jsonc` vars    | Comma-separated list of allowed frontend origins |
| `DB`                   | `wrangler.jsonc` d1_databases | D1 binding, database `onbarber-db`         |

---

## Local dev

```bash
pnpm db:migrate:local   # first time: apply schema to local D1
pnpm db:seed:local      # first time: load barbers/schedules/overrides
pnpm dev
# → http://127.0.0.1:8787
```

---

## Available commands

| Command                  | Description                                              |
|---------------------------|-----------------------------------------------------------|
| `pnpm dev`                | Start `wrangler dev` (local D1, `.dev.vars` for secrets)  |
| `pnpm deploy`              | Deploy to Cloudflare Workers                              |
| `pnpm db:generate`         | Regenerate `drizzle/*.sql` after a schema change           |
| `pnpm db:migrate:local`    | Apply migrations to local D1                               |
| `pnpm db:migrate:remote`   | Apply migrations to remote D1                               |
| `pnpm db:seed:local`       | Load `drizzle/seed.sql` into local D1 (not `appointments`) |
| `pnpm db:seed:remote`      | Load `drizzle/seed.sql` into remote D1                      |
| `pnpm test`                | Run the test suite (vitest)                                |

---

## API reference

All endpoints are prefixed with `/api`.

### Public endpoints

Rate-limited to **60 requests/minute** per IP. Booking is further limited to **5 reservations/hour** per IP.

| Method | Endpoint              | Description                                 |
|--------|-----------------------|---------------------------------------------|
| `GET`  | `/barbers`            | List all active barbers                     |
| `GET`  | `/barbers/{id}/slots` | Get available slots for a barber on a date  |
| `POST` | `/appointments`       | Book an appointment                         |

#### `GET /barbers/{id}/slots`

Query params:

| Param  | Format       | Required |
|--------|--------------|----------|
| `date` | `YYYY-MM-DD` | Yes      |

Response:
```json
{
  "slots": [
    { "time": "09:00", "available": true },
    { "time": "10:00", "available": false }
  ]
}
```

#### `POST /appointments`

```json
{
  "barber_id": 1,
  "client_name": "John Doe",
  "client_phone": "+595 971 000000",
  "appointment_date": "2025-06-15",
  "appointment_time": "09:00"
}
```

### Admin endpoints (Barbman)

Require `Authorization: Bearer <BARBMAN_TOKEN>`. No rate limits.

| Method   | Endpoint                        | Description                          |
|----------|---------------------------------|--------------------------------------|
| `GET`    | `/appointments`                 | List all appointments                |
| `PATCH`  | `/appointments/{id}`            | Update appointment status            |
| `GET`    | `/barbers/{barberId}/overrides` | List schedule overrides for a barber |
| `POST`   | `/schedule-overrides`           | Create or update a schedule override |
| `DELETE` | `/schedule-overrides/{id}`      | Remove a schedule override           |

#### Appointment statuses

`pending` → `confirmed` → `completed` / `cancelled`

#### `POST /schedule-overrides`

Upserts by `(barber_id, date)`. Use `is_open: false` to close a specific date.

```json
{
  "barber_id": 1,
  "date": "2025-12-25",
  "is_open": false
}
```

Or to change hours for a day:

```json
{
  "barber_id": 1,
  "date": "2025-07-04",
  "is_open": true,
  "open_time": "10:00",
  "close_time": "15:00",
  "break_start": "12:00",
  "break_end": "13:00"
}
```

---

## Running tests

```bash
pnpm test
```

---

## Deployment (Cloudflare Workers)

> **`pnpm deploy` only ships code.** It does NOT run D1 migrations or seed data — those are separate, manual steps. If you added/changed a migration in `drizzle/`, apply it to remote **before** deploying, or the new code will hit a schema it doesn't expect.

```bash
wrangler login                        # first time only
pnpm db:migrate:remote                # apply any new/changed migrations to prod D1
wrangler secret put BARBMAN_TOKEN     # first time only
pnpm deploy
```

**First time setting up a fresh D1** (new environment, disaster recovery, etc.) also needs the seed data (barbers/schedules/overrides — NOT `appointments`, that's live customer data and isn't in `seed.sql`):

```bash
pnpm db:seed:remote
```

Cloudflare's edge (DDoS protection, TLS) sits in front automatically. The API's D1-backed rate limiters complement (not replace) Cloudflare's edge rules.

---

## License

Apache 2.0
