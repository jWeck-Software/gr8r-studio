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
| `POST /api/signup`                 | no sign-in needed: `{name, email, password}` → `202`, also for an address that has an account (its owner is emailed that instead), so the answer tells nothing; emails a link `<APP_FRONTEND_URL>/#verify/<token>`; `422 invalid` (weak password, missing name, no email address)                                         |
| `POST /api/verify-email`           | no sign-in needed: `{token}` → same body as login with `member: null` + session cookie; `404 not_found` (unknown, used or replaced link), `410 expired` (after 24 hours)                                                                                                                                                  |
| `POST /api/verify-email/resend`    | no sign-in needed: `{email}` → `202` always; emails a new link (the old one stops working) only when the address has an unverified User                                                                                                                                                                                   |
| `POST /api/password/forgot`        | no sign-in needed: `{email}` → `204` always; emails a link `<APP_FRONTEND_URL>/#reset/<token>` (valid 30 minutes, replaces an earlier one) only when the address has a User                                                                                                                                               |
| `POST /api/password/reset`         | no sign-in needed: `{token, password}` → same body as login + session cookie; the User counts as verified, their other sessions end; `422 invalid` (weak password: the link keeps working), `404 not_found` (unknown, used or replaced link), `410 expired`                                                               |
| `POST /api/workspaces`             | a signed-in User without a Workspace: `{name, url, c}` → `201`, same body as login with their new Owner Member; `422 invalid` (URL taken or no slug, colour), `409 conflict` (a Member of a Workspace already)                                                                                                            |
| `DELETE /api/workspace`            | Owners: `{name}` (the Workspace's name, exactly) → `204`; deletes everything in it and its Files' content; its Members' Users stay without a Workspace (`403 no_workspace`); `422 invalid` (another name), `403 forbidden` (not an Owner)                                                                                 |
| `POST /api/logout`                 | `204`, ends the session                                                                                                                                                                                                                                                                                                   |
| `GET /api/me`                      | same body as login, or `401`; `403 no_workspace` when the User is no Member (any more)                                                                                                                                                                                                                                    |
| `POST /api/me/password`            | `{current, new}` → `204`; this session stays signed in, the User's other sessions end and an open reset link stops working; `401 invalid_credentials` (wrong current password), `422 invalid` (weak new one)                                                                                                              |
| `GET /api/bootstrap`               | `{data, prefs, views}` for the signed-in Member; `403 no_workspace` as for `/api/me`                                                                                                                                                                                                                                      |
| `POST /api/sync`                   | `{"ops": [{op, type, id?, data?}]}` → `{"ok": true}`, `422` or `403`                                                                                                                                                                                                                                                      |
| `GET /api/invites/{token}`         | no sign-in needed: `{email, name, workspace, account}` of an open Invite; `account` says the address has a User already; `404 not_found`, `410 expired`                                                                                                                                                                   |
| `POST /api/invites/{token}/accept` | no sign-in needed: `{name, password}` → same body as login + session cookie; `404 not_found` (unknown, used or replaced link), `410 expired`, `422 invalid` (weak password), `401 invalid_credentials` (the address has a User and this is not its password), `409 conflict` (that User is a Member of another Workspace) |
| `POST /api/members/{id}/invite`    | Owners and Admins send an invited Member's Invite again: `204`; `403`, `404`, `422` for a Member who joined                                                                                                                                                                                                               |
| `POST /api/files`                  | multipart `file`, `id`, `project`, optional `task` → `201` with the File record (`type`, `size`, `by` and `at` are the server's); `422` for more than 25 MB, an id in use or a Task of another Project; `403` for Guests and Private projects one is not in                                                               |
| `GET /api/files/{id}/content`      | the File's content with its name (`Content-Disposition: attachment`), its type by extension, `nosniff` and a sandboxing CSP; `404` for a File one doesn't see, as for one that doesn't exist                                                                                                                              |
| `POST /api/files/{id}/copy`        | `{id}` → `201` with a copy of the File, content included, named like the frontend's Duplicate (`notes copy.txt`); `404`, `403`, `422` as for an upload                                                                                                                                                                    |

Errors answer as `{"error": "<code>", "message": "…"}`; without a session every endpoint except
health, login, signup, the two `/api/verify-email` ones, the two `/api/password` ones and the two
`/api/invites/{token}` ones answers `401 {"error":"unauthenticated"}`. Signing in with the right
password before verifying the address is `403 {"error":"unverified"}`; a wrong one stays
`401 invalid_credentials`.

Rate limits (`src/Security/RateLimits.php`, limits in `config/packages/rate_limiter.yaml`): past one,
the request answers `429 {"error":"too_many_requests"}` with `Retry-After` in seconds, before any
password is checked. Signing in: 5 attempts a minute per email address and IP (a successful sign-in
starts over); emails to one address (signup, a new verification link, a reset link, an Invite sent
again): 3 a quarter hour; accepting one Invite link: 5 a minute; and every one of these endpoints, also
verify-email, password reset and change: 20 a minute per IP. The dev environment allows 50 emails per
address and 1000 requests per IP, so the browser checks, which all come from 127.0.0.1, fit; the
sign-in limit is the same. The counts live in the `cache.rate_limiter` pool (the filesystem cache):
`php bin/console cache:pool:clear cache.rate_limiter` starts them over; reseeding does not. Behind a
reverse proxy, configure `trusted_proxies`, or every client counts as the proxy's IP.

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
  `/api/invites/{token}` endpoints. Its link's token (`src/Token/Tokens.php`) is valid for 7 days;
  a new link replaces the old one, and removing the Member revokes it. Accepting creates the User (password policy: `src/Security/PasswordPolicy.php`, as the
  app's) or, when the address has a User already (a removed Member's User stays), requires that
  User's current password and keeps it; then the Member is active and the controller signs them in
  (`Security::login()`).
- `src/Account/Accounts.php`: signing up, verifying, passwords, and starting and deleting a
  Workspace, the one place that knows the rules of an account. `signUp()` applies the password
  policy, then answers the same
  for every address: a new one becomes an unverified User and gets a verification link; one with a
  verified User gets a "you already have an account" email and nothing changes; one with an
  unverified User starts over (the latest name and password, a link that replaces the earlier one),
  so whoever signed up with someone else's address cannot keep it from them. `verify()` redeems the
  link (valid 24 hours) and the controller signs the User in; `resendVerification()` emails a new
  link only to an unverified User. Until then `App\Security\VerifiedUserChecker` refuses the
  sign-in, after the password check, so only someone who knows the password learns the account
  waits for verification. Accepting an Invite verifies an unverified User too (the link reached the
  address). `startWorkspace()` makes the Workspace with the User as its Owner Member, the default
  Teams (with ids of their own: Team ids are unique across Workspaces) and notification settings
  (`WorkspaceDefaults.php`, which the demo seed uses too); the frontend then syncs the first Project.
  `forgotPassword()` emails a reset link (valid 30 minutes) only to an address with a User, and the
  answer is the same either way (the email is sent in the request, so it takes longer for an
  address with an account); `resetPassword()` checks the password policy before it uses the link
  up, sets the password and verifies the User (the link reached the address; an open verification
  link stops working), and the controller signs them in. `changePassword()` needs the current
  password and revokes an open reset link. A new password ends the User's other sessions: Symfony
  compares the hash kept in the session (`User::__serialize()`) on every request, and the session
  the change was made in carries the new one. `deleteWorkspace()` is for Owners
  (`WorkspaceAccess::whyNotDeleteWorkspace()`) who confirm with the Workspace's exact name, as the
  app's dialog asks: the Workspace's row goes and everything in it with it (`ON DELETE CASCADE`), its
  open Invite links are revoked, and its Files' content goes after the commit
  (`Files::forgetWorkspace()`); its Members' Users stay, without a Workspace (`403 no_workspace`), and
  may start one of their own.
  Emails go out after the commit (`Transaction::afterCommit()`); one that can't be sent is logged.
- `src/Token/Tokens.php`: one-time links, the one place that issues and redeems their tokens, for
  Invites, email verification and password reset (`src/Token/Purpose.php` says how long each
  works). A token is 32
  random bytes as URL-safe base64, stored only as its SHA-256 hash (`one_time_token`); a subject
  (a Member's or User's id) has one open link per purpose, so issuing another replaces it; redeeming
  uses it up, and an expired one answers "expired", not "unknown". It never flushes: callers run it
  inside their transaction. Redeeming is one `DELETE … RETURNING`, so of two requests that redeem one
  link at the same time the second waits for the first's transaction and then finds it used (404),
  rather than verifying twice or failing on a new User's unique address; accepting an Invite redeems
  before it creates the User. A signup or a new Workspace that loses a race for its address or URL
  answers 202 or 422 "taken" as usual.
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
- `src/Sync/PersonalSetting.php`: the singletons that are the signed-in Member's own, one handler
  each in `src/Sync/Handler/`: Preferences `prefs` (`PreferencesHandler`), view settings `views`
  (`ViewSettingsHandler`), notification settings `notifPrefs` and `recentSearches`. A `set` replaces
  the value for the actor only, Guests included, and bootstrap returns each Member theirs (`prefs` and
  `views` at the top level, the other two in `data`); they are kept on the Member (`personal`, JSONB).
  Each is checked for its shape and may take at most 64 KB as JSON (`422`). Preferences are plain
  values (text up to 200 characters, numbers, booleans, null; at most 100), with `theme` and
  `weekStart` checked; `name` and `title` are the Member record's and are dropped. Views are objects
  by view key whose `colW` the snapshot returns as an object also when empty.
- `src/Sync/Handler/EventHandler.php` and `SavedViewHandler.php`: Events and Saved views belong to a
  Project. Whoever sees the Project sees them; whoever may change it (not Guests: `403`) puts and
  deletes them; deleting a Project deletes them in the same batch (`ProjectHandler::deleteContents`).
- `src/Demo/DemoSeed.php`: the demo Workspace, ported from `src/data/seed.js`, with its six Events and
  its Saved view, every Member's notification settings and the Owner's recent searches. Its Files get
  small placeholder content of their type (`PlaceholderContent.php`: a PNG, a one-page PDF, an empty
  archive, JSON or a note); their sizes are the demo's.
