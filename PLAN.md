# InGo Fleet Log — Laravel Migration Plan

Rebuilding the single-file Firestore tracker (`legacy/ingo-fleet-tracker.html`) as a Laravel 12 + MySQL
app inside the existing XAMPP stack, matched to the conventions already running in `d:\xampp\htdocs`
and scoped so it cannot disturb the other projects there.

- **URL:** `http://localhost/ingo`
- **Database:** `ingo_fleet` (MariaDB, root)
- **Stack:** Laravel 12 · Breeze (Blade + Alpine) · Tailwind 4 · Vite 7
- **Estimated effort:** ~7 working days

---

## 1. Verified environment

Read from the machine on 18 August 2026 — not assumed.

| Component | Found | Verdict |
|---|---|---|
| PHP | 8.2.12 | Meets Laravel 12's `^8.2`. No upgrade needed. |
| Composer | 2.10.2 | Current. |
| Node / npm | 24.17.0 / 11.13.0 | Fine for Vite 7 and Tailwind 4. |
| Database | MariaDB 10.4.32 | Works. Past upstream EOL — plan an XAMPP bump eventually, not a blocker. |
| Extensions | pdo_mysql, mbstring, openssl, zip, gd, curl, fileinfo, intl | Everything Laravel needs is already on. |
| Apache | Listens on 80, 8080, 8081 | `AllowOverride All` on htdocs, mod_rewrite loaded. |
| Port 80 | Plain htdocs subfolders | `http://localhost/ingo` is free. |
| Vhosts | 8080 → Forever, 8081 → Precious | Leave both alone. We add no vhost. |

### House style, as observed

Five of the ten projects in htdocs are Laravel. **Forever** (Laravel 12 + Breeze + Tailwind 4 + Alpine
+ Vite 7) is the closest match to what InGo needs and is the pattern being copied. **Kugawana**
confirms the subfolder deploy works end to end — `APP_URL=http://localhost/Kugawana`, a built Vite
manifest, and `@vite()` resolving correctly through the rewrite.

- **Two `.htaccess` files per project** — one at the root forwarding into `public/`, one inside
  `public/` as Laravel's standard front controller. Identical in all five.
- **Subfolder URLs** — `APP_URL=http://localhost/<Folder>`, no vhost.
- **Database-backed everything** — `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` all set to
  `database`. No Redis to install, no extra service to keep alive.
- **MySQL as root, lowercase snake_case database name** — `forever_love`, `kugawana`, `jambo`.
- **Tailwind 4 the new way** — `@import 'tailwindcss'` with `@source` directives in `app.css`, and the
  `@tailwindcss/vite` plugin. No `tailwind.config.js`, no PostCSS chain.

---

## 2. The two `.htaccess` files

> **Built and verified 18 Aug 2026.** The naive `RewriteRule ^(.*)$ public/$1` version — copied from
> Precious and Forever — does **not** work at port 80. Those two are served by vhosts with
> `DocumentRoot` already set to `public/`, so their root `.htaccess` never actually runs. What is
> below is what was tested and works. See "Two things that bit" at the end of this section.

### `d:\xampp\htdocs\ingo\.htaccess`

```apache
# Subfolder deployment for http://localhost/ingo
# Scoped to this directory — requests to sibling htdocs projects never see these rules.

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Never serve dotfiles (.env, .git) if one is requested directly
    RedirectMatch 404 /\.(?!well-known)

    # Real files that live under public/ — built assets, favicon, robots.txt.
    # The !-f guard matters: without it the rewrite below restarts the ruleset,
    # this rule then matches the root index.php shim and maps it into public/,
    # and Laravel loses its /ingo base path again.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{DOCUMENT_ROOT}/ingo/public/$1 -f
    RewriteRule ^(.*)$ public/$1 [L]

    # Everything else goes through the root shim, which loads public/index.php
    RewriteCond %{REQUEST_URI} !^/ingo/public/
    RewriteRule ^(.*)$ index.php [L]
</IfModule>
```

### `d:\xampp\htdocs\ingo\index.php` — the shim this depends on

```php
<?php

require __DIR__.'/public/index.php';
```

One line, and the same trick Kugawana already uses. It exists so `SCRIPT_NAME` stays
`/ingo/index.php`, which is what lets Symfony work out that the app is based at `/ingo`.

### `d:\xampp\htdocs\ingo\public\.htaccess`

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Why this cannot touch the other projects

- **`.htaccess` is per-directory.** Rules in `ingo/.htaccess` only ever evaluate for requests that
  resolve inside `ingo/`. A request to `/Kugawana/…` never sees them.
- **No loop between the two files.** mod_rewrite does not inherit parent rules into a subdirectory
  that declares its own `RewriteEngine On`. Once the request lands in `public/`, only the second file
  applies — which is why this same pair already works in five projects here.
- **Nothing is added at the htdocs root.** There is no `htdocs/.htaccess` today and this plan does not
  create one. That is the only file that could affect siblings, and it stays absent.
- **No Apache config edits.** No new vhost, no new Listen port, no `httpd.conf` change.

### ⚠ The real conflict risk is cookies, not rewrites

Every project on `localhost` shares one cookie namespace, and both Forever and Kugawana ship
`SESSION_PATH=/` — so their session cookies are sent to every other project on the host. They only
stay distinct because their `APP_NAME` slugs happen to differ.

Setting `SESSION_COOKIE=ingo_session` and `SESSION_PATH=/ingo` in InGo's `.env` makes the problem
unreachable in either direction. Verified in the response headers:

```
Set-Cookie: ingo_session=…; path=/ingo; httponly; samesite=lax
Set-Cookie: XSRF-TOKEN=…;  path=/ingo; samesite=lax
```

### Two things that bit, recorded so they don't bite twice

**A bare `/ingo/` 404s under the `public/$1` rewrite.** The request is a directory hit, and mod_dir
resolves it before the rewrite can help. Adding `DirectoryIndex public/index.php` turns the 404 into
a 403 rather than fixing it. The shim removes the problem: there is now a real `index.php` at the
root for mod_dir to find.

**Rewriting straight into `public/index.php` makes every route 404 — from Laravel, not Apache.** With
`SCRIPT_NAME=/ingo/public/index.php` and `REQUEST_URI=/ingo/login`, Symfony cannot derive a base path,
so Laravel tries to route the literal `/ingo/login` and finds nothing. The giveaway is that
`/ingo/public/login` returns 200 while `/ingo/login` returns Laravel's own 404 page. Routing through
the root shim keeps `SCRIPT_NAME=/ingo/index.php` and the base path resolves.

No `ASSET_URL` is needed — `@vite` correctly emits `http://localhost/ingo/build/assets/…` once the
base path is right.

---

## 3. Stack and configuration

Laravel 12, Breeze on the Blade + Alpine stack, Tailwind 4, Vite 7, MySQL. Server-rendered pages, no
SPA, no API layer to maintain. For four screens used by a handful of people in a yard, Blade with a
little Alpine is less code and fewer moving parts than Inertia or Livewire — and it matches Forever,
so anything learned transfers between the two.

### Scaffold

```bash
# from d:\xampp\htdocs
composer create-project laravel/laravel ingo-app "12.*"
# then move ingo-app/* into ingo/, keeping the original HTML under legacy/

# from d:\xampp\htdocs\ingo
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
npm run build
```

### `.env` — the settings that matter here

```dotenv
APP_NAME="InGo Fleet Log"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/ingo
APP_TIMEZONE=Africa/Harare

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ingo_fleet
DB_USERNAME=root
DB_PASSWORD=

# keeps this project's session off every other project on localhost
SESSION_DRIVER=database
SESSION_COOKIE=ingo_session
SESSION_PATH=/ingo
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
```

`APP_TIMEZONE=Africa/Harare` is the one-line fix for the UTC date bug in the old app, where
`toISOString()` reported yesterday's date before 02:00 local.

### Vite — pin the port

Vite defaults to 5173, so two projects running `npm run dev` at once fight over it.

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: { port: 5177, strictPort: true },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

For day-to-day use run `npm run build` and let Apache serve the compiled manifest — Kugawana already
proves that path resolves correctly from a subfolder. Keep `npm run dev` for active styling work only.
If assets ever 404 after a build, `ASSET_URL=http://localhost/ingo` is the fix.

---

## 4. Database design

Four tables plus Breeze's `users`.

**The important change from Firestore: mileage is no longer stored on the bike.** It is derived from
the readings, which kills the "a mistyped reading can never be corrected" problem — there is only one
place the number can live, so editing a reading fixes everything downstream automatically.

Servicing gets the same treatment. Instead of overwriting `lastServiceMileage` on the bike, each
service is a row in `service_records`. That gives history, cost tracking, and an audit trail for free.

| Table | Columns | Notes |
|---|---|---|
| `users` | breeze default + `role` | enum: admin, clerk. Replaces having no auth at all. |
| `riders` | id, name, phone, license_number, license_expiry, is_active, legacy_id, timestamps, deleted_at | Soft deletes so a departed rider's history survives. |
| `bikes` | id, reg (unique), model, rider_id, service_interval_km, service_interval_months, legacy_id, timestamps, deleted_at | Unique reg fixes the duplicate-bike gap. The months column adds time-based servicing. |
| `readings` | id, bike_id, recorded_on, mileage, note, recorded_by, legacy_id, timestamps | Unique on (bike_id, recorded_on) — one reading per bike per day. Index the pair. |
| `service_records` | id, bike_id, serviced_on, mileage, cost, notes, recorded_by, timestamps | New. Replaces mutating the bike row on "Mark Serviced". |

### Deriving status without N+1 queries

Because a validated odometer only ever rises, the current mileage *is* the maximum reading — so the
whole dashboard loads in one query:

```php
Bike::with('rider')
    ->withMax('readings as current_mileage', 'mileage')
    ->withMax('serviceRecords as last_service_mileage', 'mileage')
    ->withMax('serviceRecords as last_serviced_on', 'serviced_on')
    ->get();
```

The `bikeServiceStatus()` and `licenseStatus()` functions port across almost unchanged — they were the
cleanest part of the original. They become methods on the models, returning the same
`{level, label}` shape so one Blade component can render a badge anywhere.

### Validation, as form requests

- `StoreReadingRequest` — mileage must be ≥ the latest reading, `recorded_on` cannot be in the future,
  and the bike+date pair must be unique.
- `StoreBikeRequest` — reg required and unique, interval at least 100 km. Numbers rejected when blank
  rather than silently becoming zero.
- `StoreServiceRequest` — service mileage must be ≥ the previous service's mileage.

---

## 5. Getting the Firestore data out

All existing data is in one document, `ingoFleet/data`, which makes this the easy part.

1. **Export** — dump the document to JSON from the Firebase console or the browser console. Save to
   `storage/app/import/firestore-export.json`.
2. **Import** — one artisan command, `php artisan ingo:import-firestore`, mapping riders → bikes →
   readings in that order so foreign keys resolve.
3. **Make it idempotent** — every row keeps its old Firestore string ID in `legacy_id`, and the
   command uses `updateOrCreate` on it. Run it as many times as you like; no duplicates, and it can be
   re-run after a late reading comes in during changeover.
4. **Backfill service records** — each bike's `lastServiceDate` and `lastServiceMileage` become one
   seed row in `service_records`.
5. **Verify** — compare rider, bike and reading counts against the JSON, and spot-check that three
   bikes show the same current mileage in both systems before cutting over.

---

## 6. Replacing live sync

Firestore's `onSnapshot` gave multi-device updates for nothing, and it is the one genuine capability
being lost. Three ways back, in order of preference:

1. **Poll, for v1.** An Alpine component on the dashboard hits `/fleet/state` every 20 seconds; the
   endpoint returns the max `updated_at` across the three tables. If it changed, refresh the board.
   About 30 lines, costs nothing, and for a yard where readings arrive a few times a day it is
   indistinguishable from live.
2. **Laravel Reverb, later.** Real websockets, but it needs a long-running process that XAMPP will not
   keep alive. Worth it only if people start watching the board continuously.
3. **Nothing at all.** Honestly viable. Most of the original's sync complexity existed to solve
   problems a normal request/response app does not have — including the bug where a colleague's save
   wiped the form you were typing into.

---

## 7. Build order

| Phase | Work | Effort | Status |
|---|---|---|---|
| **PH 00** | Scaffold and prove the URL | Half a day | ✅ Done |
| **PH 01** | Schema, models, status logic, tests | 1 day | ✅ Done — 28 tests, full suite 53 green |
| **PH 02** | Firestore import command | Half a day | ⬜ Blocked on the Firestore export |
| **PH 03** | The four screens in Blade + Tailwind | 2–3 days | ⬜ |
| **PH 04** | Auth and roles | Half a day | ⬜ Breeze installed; registration still open, roles not enforced |
| **PH 05** | Utilisation, projections, CSV, polling | 1 day | ⬜ |
| **PH 06** | Cutover | Half a day | ⬜ |

### PH 00 — Scaffold and prove the URL

Create the Laravel 12 project, drop in both `.htaccess` files, create the `ingo_fleet` database, set
the `.env` above, run `php artisan migrate`, and confirm `http://localhost/ingo` serves the welcome
page. Then load Kugawana and Forever in the same browser and confirm both still work and you are still
logged in to them. **Nothing else starts until that last check passes.**

### PH 01 — Schema, models, and the status logic

Four migrations, four models with their relationships, and the ported `serviceStatus()` /
`licenseStatus()` methods. Factories and a seeder for realistic test data.

Write the tests here, while the logic is fresh and small — service intervals, remaining kilometres and
licence-expiry windows are pure functions and the calculations the business actually depends on.

### PH 02 — The Firestore import command

Export the document, write `ingo:import-firestore`, run it against real data, and verify counts. Doing
this before the UI means every screen afterwards is developed against the actual fleet rather than
invented rows.

### PH 03 — The four screens in Blade and Tailwind

Dashboard, Log Reading, Riders, Bikes — the same information architecture, since it was right. Rebuild
the visual identity in Tailwind: the plate strip, the mechanical odometer digits, the asphalt palette.
It reads as a motorcycle yard rather than a generic admin panel and that is worth keeping.

Controllers as resource controllers, validation in form requests, one shared status-badge Blade
component so nothing can disagree about whether a bike is overdue.

### PH 04 — Auth and roles

Breeze already gave login. Disable public registration, seed the accounts by hand, add the `role`
check so clerks can log readings while only admins edit bikes and riders. This is the finding that
made the old version urgent, and here it is nearly free.

### PH 05 — The things the old app could not do

Now that readings are queryable rather than trapped in a JSON blob: kilometres per week per bike and
per rider, a flag for bikes that missed this week's reading, projected service dates from the recent
average, and CSV export. Plus the 20-second poll for near-live updates.

This is where the rebuild starts paying for itself rather than just matching what was there.

### PH 06 — Cutover

Re-run the import to catch anything logged during the build. Have the team enter one real week in the
new app while the old one still runs. Then set the old HTML file to redirect, lock the Firestore rules
to deny all, and keep the export JSON as a cold backup.

---

## 8. Findings from the original app this plan resolves

Carried over from the code review of `legacy/ingo-fleet-tracker.html`.

| ID | Finding | Resolved by |
|---|---|---|
| SEC 01 | Database open to anyone who finds it | PH 04 — Breeze auth, server-side authorisation |
| SEC 02 | Stored XSS via unescaped `innerHTML` | PH 03 — Blade escapes by default |
| DATA 01 | Concurrent saves silently clobber each other | PH 01 — per-row writes instead of one document |
| DATA 02 | A mistyped reading can never be corrected | PH 01 — mileage derived from readings |
| UX 01 | Remote updates reset the form you are filling in | PH 03 — normal request/response |
| DATA 03 | Dates calculated in UTC in a UTC+2 yard | PH 00 — `APP_TIMEZONE=Africa/Harare` |
| DATA 04 | Validation lets bad records through | PH 01 — form requests + DB constraints |
| FEAT 01 | Readings collected then barely used | PH 05 — utilisation and projections |
| FEAT 02 | Service tracked by distance only | PH 01 — `service_interval_months` |
| OPS 01 | Poor connectivity looks like a frozen button | PH 03 — normal form posts |
| ENG 01 | No version control | PH 00 — git |
| ENG 02 | Running the Firebase compatibility SDK | Removed entirely |
| ENG 03 | Dead code left in place | Removed entirely |
| ENG 04 | No routing | PH 03 — real Laravel routes |
| ENG 05 | Tables have no search, sort, or filter | PH 03 |
| ENG 06 | No tests around the maths | PH 01 |

---

## 9. Before any code

1. **`git init`, first.** The only backup of the working product is a zip of itself sitting next to it.
2. **Export the Firestore document today.** Not on cutover day. It is the entire dataset and takes two
   minutes.
3. **Folder name is `ingo`** (lowercase) — the rewrite condition and `APP_URL` both bake it in.
