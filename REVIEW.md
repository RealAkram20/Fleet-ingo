# InGo Fleet Log — Review of the Original App

Code review of `legacy/ingo-fleet-tracker.html` (877 lines, 30.5 KB), 18 August 2026.
The migration plan in [PLAN.md](PLAN.md) is built to resolve everything below.

---

## What it is

A complete fleet-management app in one file. It tracks motorcycles, the riders assigned to them, and
the odometer readings taken each week — and from those three things works out which bikes are due for
service and which riders have a licence about to expire.

Four screens: a **Dashboard** of bike cards with an odometer readout and a service badge; **Log
Reading** for the weekly odometer entry; and **Riders** and **Bikes** for the records themselves. Data
lives in Firebase Firestore and syncs live, so a reading entered on a phone in the yard appears on the
office screen a second later without a refresh.

There is no server, no build, no install. You open the file and it works.

### How the data moves

Everything — riders, bikes and readings — is held in a single Firestore document at `ingoFleet/data`.
On load, `onSnapshot` pulls that document into a global `DATA` object and renders. Any change writes
the entire object straight back with `set(DATA)`, which fires the snapshot listener on every other
open device, which re-renders everything.

About the simplest sync design that works, and for a handful of bikes it does work. The trouble starts
at the edges: two people saving at once, a document that keeps growing, and no lock on the door.

---

## What's already right

- **The status logic is honest.** `bikeServiceStatus()` and `licenseStatus()` both return the same
  `{level, label}` shape, and every renderer consumes that one shape. Cards, tables and the summary
  strip can never disagree about whether a bike is overdue.
- **Deletes clean up after themselves.** Removing a rider unassigns their bikes; removing a bike
  removes its readings. Both ask first.
- **The odometer check is the right instinct.** Rejecting a reading lower than the mileage on file
  catches the most common yard error — a transposed digit — before it lands.
- **Live sync is the correct call.** A shared yard board that updates itself is worth far more than a
  page people have to remember to refresh.
- **The design has a point of view.** Plate strips, mechanical odometer digits, an asphalt palette. It
  looks like it belongs to a motorcycle yard rather than a generic admin panel, and that matters for
  whether people actually use it.

---

## Findings

Sixteen, ranked. The two critical ones are both exploitable by anyone who learns the URL, and they
compound each other.

### SEC 01 — Critical — The database is open to anyone who finds it

There is no authentication anywhere in the file — no `firebase.auth()`, no sign-in, no user check. The
app reads and writes `ingoFleet/data` directly, which means the Firestore rules are almost certainly
still in test mode. Anyone who opens the page, or who reaches the project from any other client, can
read every rider's full name, phone number, licence number and licence expiry — and can erase the
entire fleet with one write.

To be clear about the API key sitting in the source at line 492: that is normal and fine. Firebase
keys are public identifiers, not secrets. The security boundary is rules plus authentication, and
right now there is neither.

**Fix:** Firebase Auth plus `allow read, write: if request.auth != null;` — or, as planned, move to
Laravel where this is Breeze's job.

### SEC 02 — Critical — Any stored text can execute as code

Every render path builds HTML by string interpolation and assigns it with `innerHTML` — bike cards,
both tables, the dropdowns (lines 587, 630, 687, 767). Nothing is escaped. A rider saved with the name
`<img src=x onerror="…">` runs that script in the browser of everyone who opens the dashboard,
permanently, until someone deletes the record.

On its own this needs a hostile insider. Combined with SEC 01, an outsider can plant it.

**Fix:** Blade escapes by default, so this disappears in the rebuild.

### DATA 01 — High — Two people saving at once silently lose one of the changes

Every save rewrites the whole dataset: `fleetDocRef.set(DATA)` at line 537. If the yard clerk logs a
reading while you are editing a bike, both of you are writing your own full copy of the world. Last
write wins, the other change vanishes, and neither person sees an error.

The same design carries a second problem: a Firestore document is capped at 1 MiB. Weekly readings for
thirty bikes over three years is roughly 4,700 records in that one document, and every single write
ships all of them to every connected device.

**Fix:** Real rows in real tables.

### DATA 02 — High — A mistyped reading can never be corrected

Readings can be added but never edited or deleted, and saving one overwrites the bike's
`currentMileage`. Type 145200 instead of 14520 and the bike's mileage is wrong forever — you can patch
the bike record by hand, but the bogus reading stays in history and the service maths built on top of
it stays wrong.

The root cause is that mileage is stored in two places that can disagree: on the bike, and in the
readings.

**Fix:** Derive mileage from readings. One source of truth, corrections just work.

### UX 01 — High — Someone else's save resets the form you are filling in

The snapshot listener calls `renderAll()` (line 869), which calls `renderLogReadingBikeOptions()`,
which rebuilds the bike dropdown and resets the date field to today. So when a colleague saves
anything at all, your bike selection and your chosen date reset under your cursor mid-entry.

It also fires on your own save. Log a reading for the third bike and the dropdown jumps back to the
first — while the history panel underneath, and the success message above it, now describe different
bikes.

**Fix:** Normal request/response pages have no such problem.

### DATA 03 — Medium — Dates are calculated in UTC, but the yard is on UTC+2

`todayStr()` at line 505 takes the date out of `toISOString()`, which is UTC. Between midnight and
02:00 Harare time it returns yesterday. Anything logged on an early start — and "Mark Serviced Today"
on the dashboard — records the wrong day.

**Fix:** `APP_TIMEZONE=Africa/Harare`.

### DATA 04 — Medium — Validation lets bad records through

Four gaps. Blank mileage fields become `0` silently, because of `parseFloat(…) || 0` in the bike form
(lines 793–796). Duplicate registration numbers are accepted, so the same bike can exist twice.
Nothing checks that the last-service mileage is below current mileage. And reading dates can be in the
future or out of order — a back-dated reading still overwrites current mileage as though it were the
newest.

**Fix:** Form requests plus database constraints.

### FEAT 01 — Medium — The readings are collected and then barely used

The weekly reading is the most valuable data in the system, and the only thing done with it is
printing the last eight entries as plain lines. Everything a fleet actually wants to know is already
sitting in that array: kilometres per week per bike, which riders are under-utilised, which bikes
missed a reading this week, and the projected date each bike will hit its service interval.

That last one turns the tool from a record of what happened into a plan for next week — and it needs
no extra data entry at all.

### FEAT 02 — Medium — Service is tracked by distance only

Workshop schedules are almost always distance *or* time, whichever comes first. There is only
`serviceIntervalKm`, so a bike sitting idle for eight months reports a comfortable OK right up until
it is started.

### OPS 01 — Medium — Poor connectivity looks like a frozen button

Offline persistence is never enabled. When the connection drops, the `set()` promise does not reject —
it simply never settles, so the code sits on `await` forever and the "check your internet connection"
alert never appears. The user sees a button that did nothing and types the reading again.

### ENG 01 — Low — No version control, and the backup is a zip beside the file

The folder holds the app and `ingo-fleet-tracker.html.zip`, which contains an identical copy. One bad
save overwrites the only working version of the entire product.

### ENG 02 — Low — Running the compatibility SDK, not the current one

Both script tags load `firebase-*-compat.js` — the shim kept around so v8 code can run on v10. It
works, but ships more code than needed and sits on the migration path rather than the supported one.

### ENG 03 — Low — Dead code left in place

`suppressNextSnapshotRender` is declared at line 509 and never read again. `fbApp` is assigned and
never used. `measurementId` is configured but Analytics is never loaded.

### ENG 04 — Low — No routing, every refresh lands on the Dashboard

Tabs are pure DOM toggles with nothing in the URL, so you cannot bookmark the Bikes screen, cannot
send anyone a link to it, and lose your place on every reload.

### ENG 05 — Low — Tables have no search, sort, or filter

Fine at six bikes. At forty, finding one registration means scrolling and reading, and there is no way
to pull up just the overdue ones — which is the actual question the tool exists to answer.

### ENG 06 — Low — No tests around the maths the business depends on

At 877 lines in one file, having no test setup is defensible. But service intervals, remaining
kilometres and licence-expiry windows are exactly the calculations that must not quietly break — and
they are also the easiest things in the codebase to test, being pure functions already.

---

## Summary

| Severity | Count |
|---|---|
| Critical | 2 |
| High | 3 |
| Medium | 5 |
| Low | 6 |
| **Total** | **16** |

The rebuild path for each is mapped in [PLAN.md](PLAN.md) §8.
