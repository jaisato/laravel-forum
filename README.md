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

## Security notes

Real secrets were previously committed in `.env.local`, `.env.testing` and `.env.example`. Even though these files have been removed or sanitized, the values remain in the git history and must be considered **compromised**:

- The `APP_KEY` must be rotated: run `php artisan key:generate`.
- The MySQL `DB_PASSWORD` must be changed on the database server and in your local `.env` files.

Real environment files (`.env`, `.env.local`, `.env.testing`, etc.) must never be committed; only keep a sanitized `.env.example` in the repository.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
