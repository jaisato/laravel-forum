<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About Forum

This project is a Forum web application make with Laravel. 
This repository is a project build with Laravel 11, Inertia, JetStream and Vue3. 

## Requirements

To execute or work with this project, the next requirements is a must:

- PHP 8.2
- Composer
- Docker and Docker Compose

## Installation and Configuration

This project is configured with Sail and Docker.

Configuration and installation steps:

- Download or clone the repository
- Go to root folder in the terminal
- Execute: composer install
- Execute: ./vendor/bin/sail up -d

There are 3 Docker containers:

- laravel.test (image Sail 8.3): web server
- mysql (image MySQL 8.0.32): MySQL server for databases
- mailpit (image Mailpit latest): email service to send and receive emails in local environment

## Tests

```bash
composer install
vendor/bin/pest
```

That is the whole procedure: no `.env` to create and no key to generate. The
suite reads the tracked `.env.testing` (selected by `APP_ENV=testing` in
`phpunit.xml`) and runs against an in-memory SQLite database, so it needs no
database server and leaves nothing behind.

CI runs `vendor/bin/pest --fail-on-warning`. Warnings are failures here for a
reason: the suite's last two outages were a `tests/Unit` directory named in
`phpunit.xml` but absent from the repository, which aborted PHPUnit before any
test ran, and a missing environment file, which attached a PHP warning to every
test while they all still reported green.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Security notice: rotate the previously committed credentials

`.env.local`, `.env.testing` and `.env.example` were tracked in git with a real
`APP_KEY` and database password. They have been untracked and `.gitignore` now
covers every `.env.*` file except `.env.example`, but **the values remain in the
git history** and must be treated as compromised:

1. Generate a new application key: `php artisan key:generate`.
   Note this invalidates existing sessions, signed URLs, and anything else
   encrypted with the old key.
2. Change the `forum_user` / `root` database passwords on every environment that
   used them.
3. Purge the values from history (`git filter-repo` or BFG) and force-push if
   this repository is or ever was public.

Copy `.env.example` to `.env` locally and fill in your own values.

`.env.testing` is tracked again, but it is not the file that leaked: its
`APP_KEY` was generated fresh and shares nothing with the compromised one, and
every other value in it is a fixture — an in-memory database, array mail and
cache drivers — that addresses no real service. Nothing in it is a credential,
which is why it can live in the repository and let a clean clone run the suite.
Deployed values stay in the untracked `.env`.
