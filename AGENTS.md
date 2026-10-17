# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

The repository root is the Symfony app: `src/`, `tests/`, `config/` and `migrations/` are PHP.
The frontend (plain JavaScript, built by Vite) lives in `assets/` (`assets/src/` is its source);
`npm run build` writes it into `public/` next to `index.php` and `.htaccess`, and that output is
git-ignored. Never let a frontend build empty `public/`. See `README.md` for both halves.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Deviations in this project

Where this backend knowingly departs from the conventions above, and why:

- **Records are validated by `App\Sync\Fields`, not the Validator.** `#[MapRequestPayload]` binds
  only the sync envelope (`App\Sync\Batch`, `{ops: [...]}`). The records inside the ops keep the
  frontend's shape (ADR 0002), differ per collection, and are checked against what is stored (what a
  Member may change); each handler reads them through `Fields`, which names the field in the 422.
- **Access is `App\Access\ProjectAccess` and `App\Access\WorkspaceAccess`, services, not voters.**
  The snapshot filters whole lists (Projects, Tasks, Comments, Activity) down to what a Member sees;
  voters decide one subject at a time. The snapshot and every sync handler ask `ProjectAccess`
  (Projects and what is inside them) or `WorkspaceAccess` (Roles, Teams, Invites, removing Members,
  Workspace settings), so the rules live in one place each. `WorkspaceAccess` answers with the
  reason for a refusal, which the handler sends as the 403's message.
- **The sign-in body is read by the firewall's `json_login`** (as the spec says), not by
  `#[MapRequestPayload]`; `AuthController::login` only answers what `json_login` doesn't take.
- **The password policy is `App\Security\PasswordPolicy`, not a Validator constraint.** Accepting an
  Invite applies it only when a new User is created; when the address has a User already, that
  User's current password is checked instead, which a constraint on the request DTO can't tell apart.
  Signing up (`App\Account\SignUps`) calls the same policy, so both say it in the same words.
- **Routes never name a placeholder `{id}`: `{invited}`, `{file}` and so on.** Doctrine's entity
  value resolver maps an `{id}` placeholder onto a `Member $member` argument before
  `CurrentMemberResolver` runs, so the signed-in actor would become whatever the id names
  (`POST /api/members/{invited}/invite`, `GET /api/files/{file}/content`; the README writes `{id}`
  for callers).
- **The 25 MB limit of an upload is checked by `App\File\Files`, not an `Assert\File` constraint.**
  `#[MapRequestPayload]` binds the multipart body (`App\File\NewFile`), but a file over PHP's
  `upload_max_filesize` arrives as an upload error rather than a size, and both have to be refused
  the same way, next to the other rules of storing a File.
- **Rate limits are one `kernel.request` listener (`App\Security\RateLimits`), not the firewall's
  `login_throttling` plus per-controller checks, and it reads the email address from the JSON body
  itself (`Request::toArray()`), not through `#[MapRequestPayload]`.** It runs after the router and
  before the firewall, so a refused sign-in checks no password (routes that need a session count
  after the firewall, so requests without one can't use up a Member's limits), and one map says which route counts
  against which limiter for sign-in, signup, verification, Invites and passwords alike, all with the
  same 429 body and an exact `Retry-After` (`login_throttling` answers through the failure handler
  in whole minutes). The body is read before `json_login` and the controllers' payloads are.
- **`App\Token\Tokens::redeem()` is one `DELETE … RETURNING` through DBAL, not `remove()` and a
  flush.** Taking the link has to happen at once, inside the caller's transaction, so that a second
  redemption of the same link waits for the first and then finds nothing; the ORM's remove runs only
  at the flush, after the caller has acted on the link.
- **Numbering Tasks locks the Project row (`FOR UPDATE`), not `symfony/lock`.** The lock has to last
  exactly as long as the sync transaction that stores the keys, and the database is what every
  server shares.
- **Code says "account" where CONTEXT.md says User.** The `App\Account` namespace (`SignUps`,
  `Passwords`, `Workspaces`), `AccountController`, `AccountRefused` and the `account` key of an
  Invite link's body (whether the address has a User already) keep the name: they gather what a User does for themselves (signing up, verifying,
  passwords, starting and deleting their Workspace). New domain names, messages and docs say User.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run
`php bin/phpunit` (falls back to `vendor/bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). Run `vendor/bin/php-cs-fixer fix` if `friendsofphp/php-cs-fixer` is
installed; it isn't part of the skeleton by default.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
