# Subscription Management System

A multi-tenant Laravel application. Each tenant has its own database, its own users and
its own subscription window; a central super-admin panel manages them all.

- **Tenant app:** `https://example.com/{tenant}/app` — Filament panel, per-tenant database
- **Super admin:** `https://example.com/admin` — Filament panel, central database

**[Read the architecture documentation →](docs/multi-tenancy.md)**

## Requirements

- PHP **8.4+** (Laravel 13 depends on Symfony 8, which requires `>= 8.4.1`)
- MySQL 8
- Composer 2

## Quick start

```sh
mysql -uroot -e "CREATE DATABASE sms_central; CREATE DATABASE sms_testing;"

composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan filament:assets

php artisan super-admin:create
php artisan tenant:create Acme acme \
    --admin-name="Acme Admin" --admin-email=admin@acme.test --admin-password='Sup3rSecret!23'

php artisan serve
```

Then sign in at `/admin` as the super admin, or at `/acme/app` as the tenant admin.

## Tests

```sh
php artisan test                          # runs against real MySQL
php artisan tenant:prune-test-databases   # cleanup after an interrupted run
```

---

<p align="center">Built on Laravel</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
