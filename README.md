# Campus Notes: security review project

This application is intentionally vulnerable educational material. Run it only on an isolated machine you control, with synthetic data and a disposable local database. Never expose it to the internet, deploy it, or connect it to shared or real accounts. The companion laboratory handout defines the assignment.

## Local setup

1. Install a supported PHP 8 release with `pdo_mysql` and a local MySQL server. Confirm `pdo_mysql` appears in `php -m`.
2. As a local MySQL administrator, import `schema.sql` into a fresh disposable server/database. It creates `notes_lab` and its tables; use a fresh database to avoid conflicts. Review the SQL before running it.
3. Create a dedicated local MySQL account with SELECT, INSERT, UPDATE, and DELETE privileges only on `notes_lab`; do not use a production account. Set `LAB_DB_USER` and `LAB_DB_PASSWORD` for your shell. In PowerShell use `$env:LAB_DB_USER = 'your_local_user'` and `$env:LAB_DB_PASSWORD = 'your_local_password'`. Optional: `LAB_DB_HOST` (default `127.0.0.1`) and `LAB_DB_NAME` (default `notes_lab`). Do not commit credentials.
   - The app refuses to start without `LAB_DB_USER` and will not connect as `root`.
   - If you serve the app through XAMPP/Apache instead of `php -S`, shell variables are not passed to PHP. Copy `src/config.local.example.php` to `src/config.local.php` and fill in your local lab account. That file is git-ignored.
   - If your database was imported from the original MD5 schema, run `migrations/001_rehash_seed_users.sql` once (or drop and re-import `schema.sql`).
4. From this folder, run `php -S 127.0.0.1:8000`. Visit `http://127.0.0.1:8000`. The PHP built-in server is for local development only. Stop it with Ctrl+C after use.
5. The schema includes two synthetic accounts and notes:
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

   These public, throwaway credentials are for the isolated lab only; never reuse them elsewhere. Use separate browser profiles for separate sessions. Passwords are now stored with `password_hash()` (bcrypt); the seed hashes in `schema.sql` were generated that way.

The local HTTP setup is for the exercise only. A production deployment requires HTTPS, secure cookies, protected secrets, proper error handling, and additional security controls. Students should document their own fixes and limitations.

## API

`api.php` uses the same login session as the web app and is same-origin only (no CORS headers).

- `GET /api.php` lists the signed-in user's notes. `?user_id=` is accepted only if it equals the signed-in user (otherwise 403).
- `POST /api.php` updates one of the signed-in user's notes. It requires `Content-Type: application/json`, an `X-CSRF-Token` header (the value is in the page's `<meta name="csrf-token">`), and a JSON object with exactly `id` (integer), `title` (1-120 chars) and `body` (1-10000 chars). Notes owned by someone else return 404.
- Errors: 401 not signed in, 403 bad origin/CSRF/other user, 400 malformed JSON or unknown fields, 415 wrong content type, 422 invalid values, 405 other methods.

## Project structure

```
index.php                 web controller (routes POST actions, renders template)
api.php                   JSON API
src/bootstrap.php         error settings + loads the files below
src/config.php            reads DB settings from env / config.local.php
src/config.local.example.php  template for XAMPP setups (copy to config.local.php)
src/db.php                PDO connection (exceptions, real prepared statements)
src/session.php           secure session start, login regeneration, logout
src/csrf.php              CSRF token helpers
src/helpers.php           e() escaping, redirect, flash, render, validation, headers
src/NoteRepository.php    all note SQL, always scoped to the owner
templates/home.php        HTML view
assets/style.css          styles (external so the CSP can forbid inline styles)
migrations/001_rehash_seed_users.sql  upgrade seed users from MD5 to bcrypt
.htaccess                 blocks src/, templates/, migrations/ and *.sql under Apache
```

## Known limitations

- No login rate limiting or account lockout yet.
- Not tested over HTTPS; in production the cookie `Secure` flag and HSTS must be enabled behind TLS.
- The `role` column is stored but no admin features exist; registration always creates `student`.
- No password reset or email verification.
