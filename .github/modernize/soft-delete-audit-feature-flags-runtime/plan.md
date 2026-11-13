# Modernization Plan: Safer Management Data and Optional Runtime Features

**Project**: Proyecto_React

## Technical Framework

- **Language**: PHP (Composer constraint `^8.3`; local PHP 8.5)
- **Framework**: Laravel `^13.17`
- **Frontend**: React 19 / Inertia 3
- **Build tools**: Composer, npm, Vite Plus
- **Database**: Supabase PostgreSQL; schema snapshot at
  `database/database.sql`
- **Runtime**: Docker PHP-FPM by default; current app image uses PHP 8.4

## Overview

Add recoverable deletion, privacy-conscious business auditing, and opt-in
runtime/feature controls without disrupting existing management workflows.
Preserve the current PHP-FPM default and existing workspace changes. Keep
database changes reproducible through Laravel migrations and the Supabase SQL
snapshot; do not apply migrations to a deployed or real Supabase database.

The current routes and pages include document management and project
attachments. No Reports route/module was found during inspection, so this plan
does not invent one or add a Reports flag; report this limitation unless an
existing reporting feature is identified during implementation.

## Scope and Constraints

- Soft-delete clients, projects, and financial documents. Restore only the
  explicitly selected record; never imply cascading restore.
- Respect existing foreign-key restrictions and intended child-row cascades.
  Soft deletion must not physically remove project files or their stored
  content; permanent cleanup must remain explicit and safe.
- Audit business-model changes with actor, action, entity, timestamp, and
  allowlisted old/new values. Exclude secrets, passwords, file records, and
  file contents.
- Use Laravel Pennant for existing document management and project-attachment
  functionality, enabled by default.
- Redis and Octane are optional Docker profiles. PHP-FPM remains the default.
  Verify Composer, PHP, Laravel, server, and long-lived-worker compatibility
  before adding Octane dependencies or changing runtime configuration.
- Preserve all pre-existing dirty and untracked work. In particular, make
  narrow additive edits to `Dockerfile`, `compose.yaml`, `compose.dev.yaml`,
  `README.md`, and `database/database.sql`; do not replace or reset them.
- Update relevant existing documentation. Run migrations/tests only against a
  disposable local/test database, never a deployed or real Supabase database.

## Execution and Validation

Execute the task records in `.metadata/tasks.json` in dependency order. Use
focused Pest feature/unit tests for soft-delete/restore boundaries and audit
redaction, plus relevant flag and runtime checks. Run Pint for changed PHP
files. Run frontend checks, type checks, and a production build only if the
React/UI surface is changed. Validate Compose configuration and optional
profiles without changing the default PHP-FPM startup path.

