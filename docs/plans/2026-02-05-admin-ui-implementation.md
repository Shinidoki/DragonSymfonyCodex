# Admin UI MVP (DB Login + Worlds/Characters Stats) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a database-backed login and a server-rendered admin UI to view world/character stats, create worlds, and
advance simulation days.

**Architecture:** Use Symfony Security form-login backed by a Doctrine `User` entity. Admin UI is Twig-rendered with
PRG (Post/Redirect/Get) forms and minimal JS (Turbo optional/default). Reuse existing application handlers for
mutations (`CreateWorldHandler`, `AdvanceDayHandler`).

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, Twig, Symfony Forms, Symfony Security, PHPUnit.

---

### Task 1: Add database-backed `User` entity

**Files:**

- Create: `src/Entity/User.php`
- Create: `src/Repository/UserRepository.php`
- Modify: `config/packages/security.yaml`
- Create: `migrations/*_create_user_table.php` (generated)

**Step 1: Generate user entity (MakerBundle)**

Run: `php bin/console make:user`

Choose:

- User class name: `User`
- Store in database: yes (Doctrine entity)
- User identifier: `username`
- Add password field: yes

**Step 2: Review/adjust entity**

Ensure the entity has:

- `username` unique + not blank
- `roles` JSON
- hashed `password`
- (optional but recommended) `createdAt` `DateTimeImmutable`

**Step 3: Update security config to use entity provider**

Edit `config/packages/security.yaml`:

- provider uses `entity: { class: App\Entity\User, property: username }`
- access control for admin: `^/admin` requires `ROLE_ADMIN`
- `/login` is public (`PUBLIC_ACCESS`)

**Step 4: Create and run migration**

Run:

- `php bin/console doctrine:migrations:diff --no-interaction`
- `php bin/console doctrine:migrations:migrate --no-interaction`

Expected: new `user` table created.

---

### Task 2: Add form-login authentication (MakerBundle)

**Files:**

- Create: `src/Security/*Authenticator.php` (Maker-generated)
- Create: `src/Controller/SecurityController.php` (Maker-generated)
- Create: `templates/security/login.html.twig` (Maker-generated)
- Modify: `config/packages/security.yaml`

**Step 1: Generate form login**

Run: `php bin/console make:auth`

Choose “Login form authenticator”, use:

- Authenticator class: `LoginFormAuthenticator`
- Controller: `SecurityController`
- Route: `/login`

**Step 2: Configure firewall**

Ensure `config/packages/security.yaml` `main` firewall contains:

- `form_login` (or authenticator equivalent) and a `logout` route
- entry point redirects to `/login`

**Step 3: Manual verification**

Run local server: `php -S localhost:8000 -t public`

Expected:

- `GET /login` renders login form
- `GET /admin` redirects to `/login` when logged out

---

### Task 3: Add console command to create users/admins

**Files:**

- Create: `src/Command/AppUserCreateCommand.php` (name can vary)

**Step 1: Generate command skeleton (MakerBundle)**

Run: `php bin/console make:command app:user:create`

**Step 2: Implement**

Command behavior:

- Required option: `--username`
- Optional flag: `--admin` (adds `ROLE_ADMIN`)
- Prompt for password (hidden input)
- Hash via `UserPasswordHasherInterface`
- Persist via `EntityManagerInterface`
- Fail if username exists

**Step 3: Manual verification**

Run: `php bin/console app:user:create --username=admin --admin`
Expected: prints “Created user …”.

---

### Task 4: Add admin UI (Twig controllers + forms)

**Files:**

- Create: `src/Controller/Admin/AdminDashboardController.php`
- Create: `src/Controller/Admin/AdminWorldController.php`
- Create: `src/Controller/Admin/AdminCharacterController.php`
- Create: `src/Form/CreateWorldType.php`
- Create: `src/Form/AdvanceSimulationType.php`
- Create: `templates/admin/layout.html.twig`
- Create: `templates/admin/dashboard.html.twig`
- Create: `templates/admin/world/index.html.twig`
- Create: `templates/admin/world/show.html.twig`
- Create: `templates/admin/character/show.html.twig`
- Modify: `templates/base.html.twig` (optional: basic nav + flashes)
- Modify: `assets/styles/app.css` (minimal admin styling)

**Step 1: Implement `/admin` dashboard**

Dashboard shows:

- total worlds
- total characters
- links to worlds list

**Step 2: Implement `/admin/worlds`**

World list table:

- id, seed, currentDay, createdAt, character count

Create world form:

- field: `seed`
- submit: calls `CreateWorldHandler::create($seed)`
- redirect to new world detail with flash.

**Step 3: Implement `/admin/worlds/{id}`**

Show:

- world header (seed, day, planet, map size)
- advance simulation form (`days`)
    - calls `AdvanceDayHandler::advance($worldId, $days)`
    - redirect back with flash (new current day)

Characters table for world:

- id, name, race, location, money
- strength + kiControl with tier label (`StatTier::fromValue(...)->label()`).

**Step 4: Implement `/admin/characters/{id}`**

Show character details and key stats.

---

### Task 5: Add minimal functional tests

**Files:**

- Create: `tests/Admin/AdminSecurityTest.php`

**Step 1: Write tests**

Test cases:

- Unauthenticated `GET /admin` redirects to `/login`
- Authenticated admin can load `/admin/worlds`

**Step 2: Run**

Run: `php bin/phpunit tests/Admin/AdminSecurityTest.php`
Expected: PASS.

---

### Optional: Commit checkpoints (Dennis runs manually)

- After auth works
- After admin UI pages render
- After tests pass

