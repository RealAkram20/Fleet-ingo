# Deploying InGo Fleet Log to shared hosting

Target: **https://fleet.ingo.co.zw** on a cPanel-style shared host.
Requirements: PHP **8.2 or newer** with the usual extensions (mbstring, openssl,
pdo_mysql, fileinfo, gd, ctype, tokenizer, xml, bcmath), MySQL 5.7+/MariaDB 10.3+,
Apache with `mod_rewrite`.

The deployment zip already contains `vendor/` and the built assets under
`public/build/`, so **no Composer, Node or SSH is needed** on the server.

## 1. Database

1. cPanel → *MySQL® Databases*: create a database and a user, give the user
   **All privileges** on it. Note the three names (they get a `cpaneluser_` prefix).
2. cPanel → *phpMyAdmin* → select the new database → *Import* → choose **one** of:
   - `fleet_ingo_live.sql` — clean go-live database: the two accounts and the
     default settings, no bikes or riders. **Use this for production.**
   - `fleet_ingo_demo.sql` — the same plus a sample fleet (8 riders, 10 bikes,
     ~100 readings) for training. Do not put this on the live site.

## 2. Files

Upload the zip to the server and extract it so that the folder for the subdomain
contains `app/`, `public/`, `vendor/`, `artisan`, … directly.

### Document root — do this one thing right

Point the subdomain's **document root at the `public` folder**:
cPanel → *Domains* → `fleet.ingo.co.zw` → *Document Root* → `…/fleet.ingo.co.zw/public`
(exact path depends on where you extracted).

Only `public/` is then reachable from the web; `.env`, `storage/` and `vendor/`
are not. If the host will not let you change the document root, copy
`deploy/htaccess-project-root` to the project root as `.htaccess` (overwriting
the localhost one) — it blocks the internal folders and routes everything
through `public/` from there.

## 3. Configuration

1. Copy `deploy/env.production` to the project root as `.env`
   (the zip already contains a ready `.env` with the APP_KEY filled in).
2. Fill in `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 1.
3. Make sure these folders are writable by PHP (755 normally suffices on cPanel;
   use 775 if the site errors on first load):
   - `storage/` and everything beneath it
   - `bootstrap/cache/`
   - `public/branding/` (created on first logo upload — create it if uploads fail)

`.env` values that matter and are already set for you:

| Key | Value | Why |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | never show stack traces to the public |
| `APP_URL` | `https://fleet.ingo.co.zw` | drives HTTPS links even behind a proxy |
| `SESSION_PATH` | `/` | the app is at the root of the subdomain (locally it was `/ingo`) |
| `SESSION_SECURE_COOKIE` | `true` | the session cookie only travels over HTTPS |
| `QUEUE_CONNECTION` | `sync` | shared hosting has no queue worker |
| `LOG_CHANNEL` | `daily` | one file a day under `storage/logs`, kept 14 days |

## 4. First sign-in

Open https://fleet.ingo.co.zw — it redirects to the sign-in page. Sign in with
the admin account you were given, then:

1. **Users** → change both passwords, or create the real accounts and remove the
   starter ones (the app will not let you delete the last admin).
2. **Settings → Outgoing email**: enter the host's SMTP details and press
   *Send Test*. Password resets depend on this.
3. **Settings → Branding**: upload the logo/icon if they should differ from the
   defaults.

## 5. If something is off

- **Blank page / 500 on first load** → `storage/` or `bootstrap/cache/` not
  writable, or `.env` missing. Look in `storage/logs/`.
- **419 "Page Expired" on sign-in** → `SESSION_PATH` is not `/`, or the site is
  reached over plain HTTP while `SESSION_SECURE_COOKIE=true`. Always use https.
- **CSS/JS missing** → the document root is not `public/` and no root
  `.htaccess` was installed (step 2).
- **Everyone throttled at sign-in / "Too many attempts"** → the site sits behind
  a proxy such as Cloudflare, so all visitors share one IP. Tell the developer;
  the proxy's addresses need adding to the trusted list in `bootstrap/app.php`.
- **"You were signed out because your session was used from a different browser"**
  → by design: a session is tied to the browser that opened it. On phones,
  toggling *Request Desktop Site* changes the browser signature and signs you out.

## Updating later

Upload the new zip over the old files (keep your `.env`, `storage/` and
`public/branding/`), then visit any page. New database migrations, if any, are
listed in the release notes with the SQL to run in phpMyAdmin.
