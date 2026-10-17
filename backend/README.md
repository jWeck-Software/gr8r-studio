# Gr8r Studio backend

Symfony 8.1 and PostgreSQL 17. The API speaks the frontend's own data shape: `GET /api/bootstrap`
returns the visible Workspace as the frontend's `S.data`, and `POST /api/sync` applies an ordered
batch of writes in one transaction (see `docs/adr/` and `CONTEXT.md` in the repository root).

Requirements: PHP 8.4 with `pdo_pgsql`, Composer 2, Docker with Compose.

## Run it

All commands run in `backend/`.

```sh
composer install

# PostgreSQL 17 on host port 5433 (container "gr8r-db"), and Mailpit (container "gr8r-mailpit"),
# which catches every email the backend sends: SMTP on 1025, inbox on http://127.0.0.1:8025
docker compose up -d --wait

# Database and schema (dev)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n

# Demo Workspace "Gr8r Studio", dated relative to today. Re-running replaces it with a fresh copy.
php bin/console app:demo:seed

# API on http://127.0.0.1:8000 (either one). PHP keeps uploads up to upload_max_filesize (2 MB by
# default) and drops request bodies over post_max_size (8 MB): raise both above the 25 MB a File may be.
symfony serve --no-tls --port=8000   # with upload_max_filesize=30M and post_max_size=32M in php.ini
php -d upload_max_filesize=30M -d post_max_size=32M -S 127.0.0.1:8000 -t public
```

Uploaded Files are kept in `var/storage/<Workspace id>/<File id>` (`FILE_STORAGE_DIR` in `.env`;
git-ignored, never under `public/`); the tests use `var/test-storage`, emptied at the start of every run.

To use it from the app, run the frontend with `VITE_API_URL=/api npm run dev` in the repository
root (see "Backend mode" in the root README).

Sign in as `hello@gr8rstudio.com` (the Owner) or any other active demo Member, such as
`sarah@gr8rstudio.com`, with the password `gr8r-studio-demo`:

```sh
curl -c cookies.txt -H 'Content-Type: application/json' \
  -d '{"email":"hello@gr8rstudio.com","password":"gr8r-studio-demo"}' http://127.0.0.1:8000/api/login
curl -b cookies.txt http://127.0.0.1:8000/api/bootstrap
```

## Tests

PHPUnit runs against the real PostgreSQL test database (`gr8r_test`); every test runs in a transaction
that is rolled back (dama/doctrine-test-bundle).

```sh
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test -n
php bin/phpunit
# The test of a File of exactly 25 MB runs only with PHP's upload limits raised; otherwise it is skipped:
php -d upload_max_filesize=30M -d post_max_size=32M bin/phpunit
```

## Endpoints

| Endpoint                           | Purpose                                                                                                                                                                                                                                                                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /api/health`                  | `{"status":"ok"}`, no sign-in needed                                                                                                                                                                                                                                                                                      |
| `POST /api/login`                  | `{email, password}` → `{user:{email,name}, member}` + session cookie                                                                                                                                                                                                                                                      |
| `POST /api/logout`                 | `204`, ends the session                                                                                                                                                                                                                                                                                                   |
| `GET /api/me`                      | same body as login, or `401`; `403 no_workspace` when the User is no Member (any more)                                                                                                                                                                                                                                    |
| `GET /api/bootstrap`               | `{data, prefs, views}` for the signed-in Member; `403 no_workspace` as for `/api/me`                                                                                                                                                                                                                                      |
| `POST /api/sync`                   | `{"ops": [{op, type, id?, data?}]}` → `{"ok": true}`, `422` or `403`                                                                                                                                                                                                                                                      |
| `GET /api/invites/{token}`         | no sign-in needed: `{email, name, workspace, account}` of an open Invite; `account` says the address has a User already; `404 not_found`, `410 expired`                                                                                                                                                                   |
| `POST /api/invites/{token}/accept` | no sign-in needed: `{name, password}` → same body as login + session cookie; `404 not_found` (unknown, used or replaced link), `410 expired`, `422 invalid` (weak password), `401 invalid_credentials` (the address has a User and this is not its password), `409 conflict` (that User is a Member of another Workspace) |
| `POST /api/members/{id}/invite`    | Owners and Admins send an invited Member's Invite again: `204`; `403`, `404`, `422` for a Member who joined                                                                                                                                                                                                               |
| `POST /api/files`                  | multipart `file`, `id`, `project`, optional `task` → `201` with the File record (`type`, `size`, `by` and `at` are the server's); `422` for more than 25 MB, an id in use or a Task of another Project; `403` for Guests and Private projects one is not in                                                             |
| `GET /api/files/{id}/content`      | the File's content with its name (`Content-Disposition: attachment`), its type by extension, `nosniff` and a sandboxing CSP; `404` for a File one doesn't see, as for one that doesn't exist                                                                                                                         |
| `POST /api/files/{id}/copy`        | `{id}` → `201` with a copy of the File, content included, named like the frontend's Duplicate (`notes copy.txt`); `404`, `403`, `422` as for an upload                                                                                                                                                                  |

Errors answer as `{"error": "<code>", "message": "…"}`; without a session every endpoint except
health, login and the two `/api/invites/{token}` ones answers `401 {"error":"unauthenticated"}`.

## Where things live

- `src/Snapshot/Snapshot.php`: what a Member sees of their Workspace, in the frontend's shape.
- `src/Sync/Sync.php`: applies a batch in one transaction. One handler per collection or singleton
  in `src/Sync/Handler/`; a new synced collection (`put`/`delete`) is one new class implementing
  `App\Sync\CollectionHandler`, a new singleton (`set`, like `projOrder`) one implementing
  `App\Sync\SingletonHandler`. When a new Task brings a key already taken in its Project, the
  server renumbers it and the answer says so: `{"ok": true, "keys": {"<taskId>": "WEB-207"}}`.
- `src/Sync/Handler/CommentHandler.php`: a new Comment is the signed-in Member's whatever the
  client sends; on someone else's Comment a Member may only add or remove their own id in the
  Reactions (`re`), and only the author deletes it, as long as they see its Task. Anyone who sees the Task may comment, Guests
  included. Deleting a Task or a Project deletes its Comments. A deleted Comment leaves a
  `DeletedComment` behind (never in bootstrap): when undo puts the same id again, the Comment comes
  back as its author's, for a Member who sees its Task, instead of becoming a new one by whoever undid.
- `src/Sync/Handler/ActivityHandler.php`: Activity is append-only. A new entry is the signed-in
  Member's; putting an id that exists changes nothing, and `delete` is accepted and ignored (undo
  in the frontend restores S.data as it was and so sends deletes). Entries about a deleted Task or
  Project stay, but bootstrap shows only those about visible Projects or about no Project.
- `src/Notification/NotificationRules.php`: which Notifications a put makes, as a pure function:
  a changed `assignee` notifies the new assignee, a new Comment notifies the Members it mentions as
  `@Full Name` (longest names first) and the Task's assignee; never the actor. A deleted Task leaves a
  `DeletedTask` behind (never in bootstrap), so when undo puts it again it is compared with the Task
  as it was and its assignee is not notified again. `Notifier.php` stores
  them for recipients who see the Task's Project; task and comment handlers call it.
- `src/Sync/Handler/NotificationHandler.php`: Notifications are the server's; their recipient may
  only change `read` (anything else, or someone else's, is 403). A Notification the client made up
  is ignored and `delete` changes nothing. Bootstrap shows the Member's own, newest first, about
  what they can still see.
- `src/Access/ProjectAccess.php`: who sees and changes which Projects (Private projects, Guests);
  used by the snapshot and by every handler.
- `src/Access/WorkspaceAccess.php`: who manages people, Teams and the Workspace's settings. Owners
  and Admins change Roles and Teams, invite, remove Members, manage Teams and rename or recolour the
  Workspace; only an Owner changes an Owner's Role, makes someone an Owner or removes an Owner; the
  Workspace keeps at least one Owner and nobody removes themselves. Everyone, Guests included,
  changes only their own name, title and avatar colour (`c`).
- `src/Sync/Handler/MemberHandler.php`: a put of an id the Workspace doesn't have is an Invite: the
  Member is stored as `invited`, without a User, and is emailed a link (`src/Invite/Invites.php`).
  A Member's new name is their User's too, so sign-in and `/api/me` show it. Email, status, last active and time zone are the
  server's; what a put sends for them is ignored. Removing a Member unassigns their Tasks and drops
  them from every Project's Members and lead; their favorites go with them, and their User stays
  without a Workspace (`403 no_workspace`).
- `src/Invite/Invites.php`: Invites, the one place that knows their links. `send()` (from the
  member handler) and `resend()` (`POST /api/members/{id}/invite`) issue a fresh link and email it
  as `<APP_FRONTEND_URL>/#join/<token>`; `describe()` and `accept()` serve the two
  `/api/invites/{token}` endpoints. A token is 32 random bytes, stored only as its SHA-256 hash,
  single-use and valid for 7 days; a new link replaces the old one, and removing the Member removes
  the Invite. Accepting creates the User (password policy: `src/Security/PasswordPolicy.php`, as the
  app's) or, when the address has a User already (a removed Member's User stays), requires that
  User's current password and keeps it; then the Member is active and the controller signs them in
  (`Security::login()`).
- `src/Persistence/Transaction.php`: the sync batch's transaction. Work deferred with
  `afterCommit()`, such as the Invite email of a sync put, runs only once the batch has committed,
  so a refused batch emails nobody. An email that cannot be sent then is logged, not an error: the
  batch is stored, and the Admin can send the Invite again.
- `src/Sync/Handler/TeamHandler.php`, `WorkspaceSettingsHandler.php`: Teams, and the `ws` singleton
  (name, URL slug like `gr8r-studio`, colour `#RRGGBB`). Deleting a Team leaves its Members and
  Projects without one (`team: ""`).
- `src/File/Files.php`: Files and their content, the one place that knows where blobs live.
  `store()` (upload), `download()`, `copy()`, `remove()` / `removeAllOf()` (a Task's or Project's)
  and `followTask()`. A File's record and blob come and go together: an upload or copy whose record
  can't be stored leaves no blob, and a removed File's blob goes only once the sync batch that
  removed it has committed (`Transaction::afterCommit()`). Content is served as an attachment with
  the type its name says, `nosniff` and `Content-Security-Policy: sandbox`, so an uploaded HTML or
  SVG file runs no script on the app's origin.
- `src/Sync/Handler/FileHandler.php`: the sync renames Files (only `name` is the client's; `type`
  follows it) and deletes them, for Members who may change the Project (not Guests). A put for a
  File the server doesn't have changes nothing: undo of a Task or Project delete puts its Files
  back, whose content is gone, and refusing them would refuse bringing the Task back. Files are
  created only by an upload or a copy.
- Attachments: a Task's `attachments` are the Files attached to it, rendered by the snapshot
  (oldest first, the File's id as the Attachment's id), not a column of the Task as the spec first
  planned; what a Task put says about them is ignored. Deleting a Task or Project deletes its Files,
  and a Task moved to another Project takes its Files along.
- `src/Demo/DemoSeed.php`: the demo Workspace, ported from `src/data/seed.js`. Its Files get small
  placeholder content of their type (`PlaceholderContent.php`: a PNG, a one-page PDF, an empty
  archive, JSON or a note); their sizes are the demo's.
