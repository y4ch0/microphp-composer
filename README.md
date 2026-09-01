# MicroPHP

MicroPHP is a small filesystem-driven PHP project skeleton. Pages live in
`app/pages`, API handlers live in `app/api`, reusable UI lives in
`app/components`, framework internals live in `src`, and the safe web document
root is `public`.

## Create a New Project

After this repository is published on Packagist:

```bash
composer create-project yacho/microphp my-app
cd my-app
php -S localhost:8000 -t public public/index.php
```

The `create-project` command runs `bin/setup-project.php`, which creates a
local `.env`, asks for the display name and database driver, and prepares the
runtime directories.

Run the setup wizard again at any time with:

```bash
composer run microphp:setup
```

Overwrite an existing `.env` and rerun the prompts with:

```bash
composer run microphp:setup -- --force
```

The setup wizard always requires an interactive terminal. Blank answers are
rejected, and optional empty values must be entered explicitly as `-`.

## Local Development

```bash
composer install
composer test
php -S localhost:8000 -t public public/index.php
```

The root `index.php` remains as a shared-hosting convenience entry point, but
production servers should point their document root to `public/`.

## Common Commands

```bash
composer run microphp:make-component -- AlertBox
composer run microphp:make-api -- /api/v1/users/:id
composer run microphp:cache-clear
composer run microphp:cache-warm
```

## Publish Checklist

1. Commit source files, `composer.json`, `composer.lock`, `.env.example`, and
   docs.
2. Do not commit `vendor/`, `.env`, `var/cache`, `var/log`, or `var/sessions`.
3. Run `composer validate --strict`.
4. Run `composer dump-autoload --optimize --strict-psr`.
5. Run `composer test`.
6. Push a Git tag such as `v1.0.0`.
7. Submit the public repository URL to Packagist.
"# microphp-composer" 
