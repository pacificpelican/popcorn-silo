# 🍿 Popcorn Silo

A simple file upload and management utility written in plain PHP. No composer, no build step.

## Features

- User accounts (email + password, hashed with `password_hash`)
- Upload files; download, rename, delete, and toggle **private** ↔ **shared**
- Per-file permalink pages (`file.php?id=N`) — visible to the owner, or to anyone if the file is shared
- Front page shows the **most recent shared file**
- Expandable **debug panel** on the front page and profile page: current data store, upload dir writability, PHP upload limits
- Purple & orange, obviously

## Requirements

- PHP 8+ (works on 7.4+)
- For MySQL storage: the `pdo_mysql` extension and a database

## Quick start (built-in PHP server)

```sh
php -S localhost:8000
```

Then open http://localhost:8000 — the app creates `data/` and `uploads/` automatically and uses the local JSON store.

## MySQL setup

1. Create the database:

   ```sql
   CREATE DATABASE popcorn_silo CHARACTER SET utf8mb4;
   ```

2. Fill in your credentials in [config.php](config.php).
3. Reload the app — tables are created automatically on first connection.

If the MySQL credentials are blank, `pdo_mysql` is missing, or the connection fails, the app falls back to local JSON files in `data/` (`users.json`, `files.json`). The debug panel always shows which store is active.

## Project layout

```
config.php          Credentials & paths
index.php           Front page: most recent shared file + links
login.php           Log in
register.php        Create account
logout.php
profile.php         Your files: upload, download, rename, share/unshare, delete
file.php            Per-file permalink page
download.php        Permission-checked download endpoint
action.php          POST handler for file actions (CSRF-protected)
lib/                Store (MySQL/JSON), auth, helpers
assets/style.css    The purple. The orange.
data/               Local JSON store (auto-created)
uploads/            Uploaded file contents (auto-created, random names)
```

## Security notes

- Passwords hashed with `password_hash`; sessions use HttpOnly cookies and regenerate on login.
- All state-changing requests require a CSRF token.
- Uploaded files are stored under random names; downloads go through `download.php`, which enforces the shared/owner permission check. Private files are never exposed at a direct URL.
- The included `.htaccess` files block direct web access to `data/` and `uploads/` on Apache. On nginx or other servers, add an equivalent rule (the built-in PHP server is dev-only anyway).
