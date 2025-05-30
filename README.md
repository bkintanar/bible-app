<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

-   **[Vehikl](https://vehikl.com)**
-   **[Tighten Co.](https://tighten.co)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel)**
-   **[DevSquad](https://devsquad.com/hire-laravel-developers)**
-   **[Redberry](https://redberry.international/laravel-development)**
-   **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Bible Reader Application

A modern Laravel-based Bible reading application with support for multiple translations and OSIS XML format.

## Features

-   **Multiple Bible Translations**: Support for KJV, ASV, and Māori translations
-   **Clean Architecture**: Separate parsers for different OSIS verse formats (milestone vs contained)
-   **Modern UI**: Beautiful, responsive interface with dark mode support
-   **Search Functionality**: Full-text search across verses
-   **RESTful API**: JSON endpoints for programmatic access
-   **Comprehensive Testing**: Full test suite with Pest PHP

## Translations Included

-   **KJV** (King James Version, 1769) - English, milestone verse format
-   **ASV** (American Standard Version, 1901) - English, contained verse format
-   **MAO** (Māori Version, 2009) - Māori, contained verse format

## Architecture

### OSIS Parser System

The application uses a clean parser architecture to handle different OSIS XML formats:

-   `OsisParserInterface` - Common interface for all parsers
-   `MilestoneOsisParser` - Handles KJV-style milestone verses (sID/eID format)
-   `ContainedOsisParser` - Handles ASV/MAO-style contained verses (osisID format)
-   `OsisReader` - Main service that auto-detects format and delegates to appropriate parser

### Services

-   `TranslationService` - Manages translation configurations and reader instances
-   `OsisReader` - Core service for reading OSIS XML files

## Testing

The application includes a comprehensive test suite built with **Pest PHP**:

### Test Coverage

-   **Unit Tests**: 52 tests covering core functionality

    -   `BibleConfigTest` - Configuration validation (13 tests)
    -   `TranslationServiceTest` - Translation management (17 tests)
    -   `OsisReaderTest` - Core OSIS reading functionality (6 tests)
    -   `MilestoneOsisParserTest` - KJV parser testing (8 tests)
    -   `ContainedOsisParserTest` - ASV/MAO parser testing (8 tests)

-   **Feature Tests**: HTTP route and web interface testing
    -   Home page functionality
    -   Translation switching
    -   Book and chapter navigation
    -   Search functionality
    -   API endpoints
    -   Error handling

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest tests/Unit/
./vendor/bin/pest tests/Feature/

# Run with custom script (organized output)
./run-tests.sh

# Run specific test groups
./vendor/bin/pest --filter="BibleConfig"
./vendor/bin/pest --filter="TranslationService"
./vendor/bin/pest --filter="Home page"
```

### Test Statistics

-   **Total Tests**: 50+ tests
-   **Assertions**: 100+ assertions
-   **Coverage**: Core services, parsers, configuration, and web interface

## Installation

1. Clone the repository
2. Install dependencies: `composer install`
3. Copy environment file: `cp .env.example .env`
4. Generate application key: `php artisan key:generate`
5. Place OSIS XML files in `assets/` directory
6. Configure translations in `config/bible.php`
7. Serve the application: `php artisan serve`

## Configuration

Bible translations are configured in `config/bible.php`:

```php
'translations' => [
    'kjv' => [
        'name' => 'King James Version',
        'short_name' => 'KJV',
        'language' => 'English',
        'year' => '1769',
        'filename' => 'kjv.osis.xml',
        'description' => 'The classic King James Version...',
        'is_default' => true,
    ],
    // ... other translations
],
```

## API Endpoints

-   `GET /api/books?translation={key}` - List all books
-   `GET /api/{book}/chapters?translation={key}` - List chapters for a book
-   `GET /api/{book}/{chapter}/verses?translation={key}` - List verses for a chapter
-   `GET /api/search?q={query}&translation={key}` - Search verses

## Development

### Adding New Translations

1. Place OSIS XML file in `assets/` directory
2. Add configuration entry in `config/bible.php`
3. The system will auto-detect the verse format and use the appropriate parser

### Code Quality

-   **SOLID Principles**: Clean separation of concerns
-   **Interface-based Design**: Extensible parser system
-   **Comprehensive Testing**: High test coverage with Pest
-   **Modern Laravel**: Uses latest Laravel features and best practices

## License

This project is open-sourced software licensed under the MIT license.
