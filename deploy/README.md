# Deploying InGo Fleet Log to Hostinger

Live target: **https://fleet.ingo.co.zw**, extracted at **`public_html/fleet`**.
Requirements: PHP **8.2+** (mbstring, openssl, pdo_mysql, fileinfo, gd, ctype,
tokenizer, xml, dom, bcmath), MySQL/MariaDB, Apache/LiteSpeed with rewrites.

The zip already contains `vendor/` and the built assets in `public/build/`, so
**no Composer, Node or SSH is needed** on the server.

---

## ⚠️ The one thing that breaks first: folder permissions

A blank white **HTTP 500 with an empty page** almost always means Laravel could
not **write to `bootstrap/cache/`** (it writes its package manifest there on the
first request) — it then dies before it can even render an error page.

**Fix, in hPanel → File Manager, inside `public_html/fleet`:**

1. `bootstrap/cache` → right-click → **Permissions → 755** (use **775** if 755
   still 500s). Apply to the files inside too.
2. `storage` → **Permissions → 755**, and **tick "recurse into subfolders"** so
   `storage/framework/views`, `storage/framework/sessions` and `storage/logs`
   are all writable. Blade compiles pages into `storage/framework/views`, so if
   this is not writable you get a second blank 500 straight after fixing the
   first.

If `bootstrap/cache` does not exist at all (FTP sometimes skips near-empty
folders), create it: open `bootstrap` → **New Folder** → `cache` → set to 755.

**Not sure what is wrong? Upload `public/healthcheck.php` (it is in the zip) and
open https://fleet.ingo.co.zw/healthcheck.php** — it lists exactly which folders
are not writable, which PHP extensions are missing, and whether the database
connects, *without booting Laravel* so it works even while the site is down.
**Delete that file once the site is up** (see the last section).

---

## 1. Database

1. hPanel → **Databases → MySQL Databases**: create a database and user (they
   get a `u338095799_` prefix), give the user access to the database.
2. hPanel → **phpMyAdmin** → select the database → **Import** → choose **one**:
   - `fleet_ingo_live.sql` — clean go-live data: the two accounts and default
     settings, no bikes or riders. **Use this for production.**
   - `fleet_ingo_demo.sql` — same plus a sample fleet for training. Not for live.

## 2. Files & document root

Extract the zip so `public_html/fleet` directly contains `app/`, `public/`,
`vendor/`, `artisan`, `.env`, …

**Two valid layouts:**

- **Best:** point the subdomain's document root at **`public_html/fleet/public`**
  (hPanel → Domains → fleet.ingo.co.zw → document root). Only `public/` is then
  web-reachable; `.env`, `storage/`, `vendor/` are not.
- **As shipped (document root = `public_html/fleet`):** the zip's root `.htaccess`
  hides the internal folders and routes everything through `public/`. This is
  what is currently live and it works; the folders are protected by that
  `.htaccess`. Prefer the first layout when you can change the doc root.

## 3. Configuration — `.env`

The zip ships a ready `.env` (APP_KEY already filled). Set the three DB lines:

```env
DB_DATABASE=u338095799_myfleet
DB_USERNAME=u338095799_myfleet
DB_PASSWORD="Myfleet26@"
```

> Wrap the password in **double quotes** if it contains a space, `#`, `"` or `'`.
> A `@` on its own (like `Myfleet26@`) is fine unquoted, but quoting never hurts.

Already set for you and correct for this host:

| Key | Value | Why |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | never show stack traces publicly |
| `APP_URL` | `https://fleet.ingo.co.zw` | drives HTTPS links even behind the CDN |
| `SESSION_PATH` | `/` | app is at the subdomain root (locally it was `/ingo`) |
| `SESSION_SECURE_COOKIE` | `true` | session cookie travels over HTTPS only |
| `QUEUE_CONNECTION` | `sync` | shared hosting has no queue worker |

## 4. First sign-in

Open https://fleet.ingo.co.zw → sign-in page. Sign in with the admin account you
were given, then:

1. **Users** → change both passwords (or make the real accounts and remove the
   starters; the app will not let you delete the last admin).
2. **Settings → Outgoing email** → enter the host's SMTP details → **Send Test**.
   Password resets depend on this.
3. **Settings → Branding** → upload logo/icon if different from the defaults.

## 5. Delete the health check

Once the site loads, remove **`public_html/fleet/public/healthcheck.php`** in
File Manager. It reports environment facts and has no place on a live site. (You
can also gate it meanwhile by setting `HEALTHCHECK_TOKEN=something` in `.env` and
visiting `/healthcheck.php?token=something`; it never prints the DB password or
APP_KEY, but delete it regardless.)

---

## Troubleshooting quick reference

| Symptom | Cause | Fix |
|---|---|---|
| **Blank page, empty 500** | `bootstrap/cache` (or `storage`) not writable | chmod 755/775 — top of this file |
| 500 with a readable error page | DB credentials / SQL not imported | check `.env` DB lines; import the SQL |
| **419 "Page Expired"** on sign-in | `SESSION_PATH` not `/`, or reached over http | use https; keep `SESSION_PATH=/` |
| CSS/JS missing | doc root not `public/` and no root `.htaccess` | step 2 |
| Everyone throttled at login | site behind Cloudflare → all share one IP | tell the dev: add proxy IPs to `bootstrap/app.php` |
| "signed out — different browser" | session tied to the browser (by design) | don't toggle Request-Desktop-Site mid-session |

## Updating later

Upload the new zip over the old files (keep your `.env`, `storage/` and
`public/branding/`), then visit any page. Any new database migrations ship with
the release notes as SQL to run in phpMyAdmin.
