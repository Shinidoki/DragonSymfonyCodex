# Admin UI MVP (Stats + Simulation Controls) — Design

## Goal

Add a simple, server-rendered admin UI to:

- Inspect world + character stats
- Create worlds
- Advance the simulation by N days

The UI should reuse existing application logic (handlers/commands), keep JS minimal, and be a stable foundation for
future admin features.

## Non-goals (MVP)

- Character CRUD (create/edit/delete)
- Map generation, travel controls
- Local-mode controls (enter session, tick actions)
- Analytics dashboards, charts, filtering, pagination

## Approach

**Server-rendered Twig + Turbo**.

- Symfony controllers render pages and handle POST actions (forms).
- Turbo provides snappy navigation after POST/redirect without custom JS.
- Use Symfony MakerBundle to generate classes where possible.

## Security & Authentication

Use **database-backed users** (future-proof for multiple admins).

- Entity: `App\Entity\User` with:
    - `username` (unique, user identifier)
    - `password` (hashed)
    - `roles` (JSON array; admin access via `ROLE_ADMIN`)
    - (optional) `createdAt`
- Auth: Symfony Security **form login** (Maker-generated authenticator + controller/template).
- Access control:
    - `/login` is public
    - `/admin/**` requires `ROLE_ADMIN`

Bootstrap first admin user via a console command:

- `php bin/console app:user:create --username=admin --admin`
- Command prompts for password, hashes it via `UserPasswordHasherInterface`, persists the `User`.

## Pages & Routes (MVP)

### Login

- `GET /login` — login form
- `POST /login` — authenticate
- `POST /logout` — logout

### Admin

- `GET /admin` — dashboard (quick links + basic counts)
- `GET /admin/worlds` — world list
- `GET /admin/worlds/{id}` — world detail + actions
- `GET /admin/characters/{id}` — character detail

## UI: What we show

### World list (`/admin/worlds`)

Table rows:

- `id`, `seed`, `planetName`, `currentDay`, `createdAt`
- `#characters` (count)

Actions:

- “Create world” button → create form (inline on page or dedicated route)

### World detail (`/admin/worlds/{id}`)

Header:

- `seed`, `planetName`, `currentDay`, `map size (width×height)`, `createdAt`

Actions:

- “Advance simulation” form: `days` (positive int)
    - Calls `App\Game\Application\Simulation\AdvanceDayHandler::advance($worldId, $days)`
    - Redirect back with a success flash message (include new day)

Characters table (for that world):

- `id`, `name`, `race`, `tileX/tileY`, `money`
- key stats: `strength`, `kiControl` (and optionally `speed`, `endurance`, `focus`, `discipline`, etc.)
- show tier labels using `App\Game\Domain\Stats\StatTier::fromValue(...)->label()`

### Character detail (`/admin/characters/{id}`)

Details:

- identity: `id`, `name`, `race`, `world`, `tileX/tileY`
- economy/work: `money`, employment fields (if present)
- core stats (same as above)

## Backend Wiring

### Controllers (new)

- `App\Controller\Admin\AdminDashboardController`
- `App\Controller\Admin\AdminWorldController`
- `App\Controller\Admin\AdminCharacterController`

Controllers use Doctrine repositories for reads:

- `App\Repository\WorldRepository`
- `App\Repository\CharacterRepository`

And reuse existing application handler for simulation:

- `App\Game\Application\World\CreateWorldHandler` (for create world action)
- `App\Game\Application\Simulation\AdvanceDayHandler` (advance days action)

### Forms (new)

- `CreateWorldType` (field: `seed`)
- `AdvanceSimulationType` (field: `days`)

Use PRG (Post/Redirect/Get) + flash messages for all POST actions.

## Templates (new)

- `templates/admin/layout.html.twig` — admin layout + nav
- `templates/admin/dashboard.html.twig`
- `templates/admin/world/index.html.twig`
- `templates/admin/world/show.html.twig`
- `templates/admin/character/show.html.twig`
- `templates/security/login.html.twig` (Maker-generated)

Keep styles minimal (use existing `assets/styles/app.css`, extend for admin layout).

## Error Handling

- World/character not found: 404.
- Invalid form input: redisplay form with validation errors.
- Simulation handler errors: show a flash error and redirect back.

## Testing (MVP)

Add a small functional test suite:

- `/admin/*` redirects to `/login` when unauthenticated.
- Authenticated `ROLE_ADMIN` user can load `/admin/worlds` and `/admin/worlds/{id}`.

## MakerBundle commands (implementation checklist)

- `php bin/console make:user` (username login)
- `php bin/console make:auth` (form login)
- `php bin/console make:command app:user:create`
- `php bin/console make:controller` (admin controllers)
- `php bin/console make:form` (world/simulation forms)
- `php bin/console make:migration` + `php bin/console doctrine:migrations:migrate`

## Next increments (after MVP)

- Create character from admin
- World map generation + travel controls
- Local mode controls
- Basic filtering/pagination on lists

