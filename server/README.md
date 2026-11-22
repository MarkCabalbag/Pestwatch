# PestWatch (Node.js + Express + Prisma)

IoT pest monitoring dashboard. The Arduino / SIM800L posts temperature, humidity and
pest-detected readings; logged-in users watch them on a live dashboard.

- **Backend:** Node.js, Express, Prisma (MySQL)
- **Frontend:** plain HTML + Tailwind (CDN) — no build step

## Setup

Requires Node.js 20+ and MySQL / MariaDB (XAMPP is fine).

```bash
npm install
cp .env.example .env      # then edit DATABASE_URL and SESSION_SECRET
npx prisma db push        # creates the pest_monitor database and tables
npm start                 # http://localhost:3000   (npm run dev = auto-restart)
```

Already have the `pest_monitor` database from the PHP version? Point `DATABASE_URL` at it
and run `npx prisma db push` — the Prisma models use the same tables and columns, and
existing users (PHP bcrypt hashes) can still log in.

## Routes

| Old (PHP)        | New                         | Notes                                         |
|------------------|-----------------------------|-----------------------------------------------|
| `login.php`      | `GET /login`, `POST /api/login`   | username **or** email                   |
| `signup.php`     | `GET /signup`, `POST /api/signup` |                                         |
| `dashboard.php`  | `GET /dashboard`, `GET /api/dashboard` | refreshes every 10 s via fetch     |
| `logout.php`     | `GET /logout`               |                                               |
| `receive.php`    | `POST /receive`             | `/receive.php` and `/iot/receive.php` also work |

`POST /receive` takes the same fields as before (`temp`, `humidity`, `pest`, `sender`,
`recipient`), form-encoded or JSON, and returns the same JSON and status codes.

Quick test:

```bash
curl -X POST http://localhost:3000/receive \
  -d "temp=28.5" -d "humidity=64" -d "pest=1" -d "recipient=+639171234567"
```

## Arduino firmware

The old URL was `http://<host>/iot/receive.php` on port 80. Either change the port in the
sketch to `3000` (or whatever `PORT` is in `.env`), or run with `PORT=80`.

## Project layout

```
prisma/schema.prisma     database models (users, sensor_logs)
src/server.js            Express app setup
src/auth.js              session helpers (was auth.php)
src/db.js                Prisma client (was db.php)
src/routes/              pages, auth (login/signup), sensors (receive + dashboard feed)
views/                   login.html, signup.html, dashboard.html
```

## Notes

- Prisma is pinned to v6 (classic `url = env(...)` datasource).
- Timestamps are stored in UTC and shown in the viewer's local time. Rows written by the
  old PHP app were stored in the MySQL server's local time, so they will look shifted by
  the UTC offset.
- Login sessions are signed cookies (`cookie-session`); set a long random `SESSION_SECRET`.
