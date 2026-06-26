# laravel-json-driver

![CI](https://github.com/gonza1212/laravel-json-driver/actions/workflows/ci.yml/badge.svg)
[![Latest Release](https://img.shields.io/github/v/release/gonza1212/laravel-json-driver?color=32a852&logo=laravel)](https://github.com/gonza1212/laravel-json-driver/releases)
![NativePHP v3](https://img.shields.io/badge/NativePHP-v3%20compatible-brightgreen)

JSON database driver for Laravel 13+. Local persistence without an external database — ideal for rapid development, prototyping, testing, and offline mobile apps where you want zero database setup.

## Requirements

- PHP 8.3+
- Laravel 13+

## Installation

```bash
composer require gonza1212/laravel-json-driver --dev
```

## Configuration

In your `.env`:

```env
DB_CONNECTION=json
DB_DATABASE=storage/app/json-db
```

The driver registers via auto-discovery. No changes to `config/app.php` or `config/database.php` required.

## Usage

### Migrations

```php
Schema::create('books', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->timestamps();
});
```

```bash
php artisan migrate
php artisan migrate:rollback
```

### Eloquent

```php
$book = Book::create(['title' => 'Clean Code']);
$book = Book::find(1);
$books = Book::all();
$book->update(['title' => 'Clean Architecture']);
$book->delete();
```

### Supported where operators

`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `in`, `not in`, `is null`, `is not null`, `between`, `not between`, `like`, `whereDate`, `whereYear`, `whereMonth`

### Order, limit, and offset

```php
$books = Book::where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(0)
    ->get();
```

### Factories and seeders

```php
Book::factory()->count(10)->create();
```

```bash
php artisan db:seed
```

### Eloquent relationships

Native relationships work out-of-the-box, without a trait or custom base class:

```php
// Lazy loading
$book->author;
$author->books;

// Eager loading
Author::with('books')->get();

// whereHas / has / withCount
Author::whereHas('books', fn($q) => $q->where('title', 'like', 'C%'))->get();
Author::has('books')->get();
Author::withCount('books')->get(); // adds books_count column

// belongsToMany with pivot columns
$book->genres()->attach($genre->id, ['order' => 1, 'added_at' => now()]);
$book->genres()->wherePivot('order', 1)->first(); // $genre->pivot->order === 1
```

FKs are declared with standard Laravel syntax. The driver respects them on delete:

```php
Schema::create('books', function (Blueprint $table) {
    $table->id();
    $table->foreignId('author_id')->constrained('authors')->restrictOnDelete();
});

Schema::create('genre_book', function (Blueprint $table) {
    $table->foreignId('genre_id')->constrained('genres')->restrictOnDelete();
    $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
});
```

- `restrictOnDelete()` (default) → throws `RuntimeException` if you try to delete a parent with related rows
- `cascadeOnDelete()` → deletes related rows before deleting the parent

## NativePHP Compatibility

`laravel-json-driver` is compatible with [NativePHP for Mobile v3](https://nativephp.com). No driver changes are required.

| Concern | Status | Notes |
|---|---|---|
| `storage_path()` persistence | ✅ Verified | iOS: redirected via `LARAVEL_STORAGE_PATH` to `Application Support/storage/` (sandbox, persistent). Android: redirected via `LARAVEL_STORAGE_PATH` to `app_storage/persisted_data/storage/` (internal app storage, persistent). |
| PHP extensions (json, pcre, date) | ✅ Verified | Android runtime (PHP 8.4.5) bundles `json` (`--enable-json`), PCRE (bundled PCRE2, `HAVE_BUNDLED_PCRE 1`), and `date` (built-in). iOS uses the same bundled PHP runtime. |
| `require-dev` excluded from production build | ✅ Verified | iOS build runs `composer install --no-dev` when the `--release` flag is passed. Android build runs `composer install --no-dev --no-interaction` by default. |

### Installation note

Since the package is installed with `--dev`, it is automatically excluded from production
builds when NativePHP runs `composer install --no-dev` during the bundle step. No additional
configuration is needed.

> Verified against NativePHP mobile [`v3.3.6`](https://github.com/nativephp/mobile-air/releases/tag/3.3.6)
> — 2026-06-26.

<details>
<summary>References in the <code>nativephp/mobile-air</code> repository</summary>

- `src/Commands/BuildIosAppCommand.php:98-112` — iOS `composer install --no-dev` on release build
- `src/Traits/PreparesBuild.php:200-256` — Android `composer install --no-dev` on bundle prepare
- `resources/xcode/NativePHP/NativePHPApp.swift:391,405` — iOS `LARAVEL_STORAGE_PATH` setup
- `resources/xcode/NativePHP/Bridge/PersistentPHPRuntime.swift:63,67` — iOS persistent runtime storage path
- `resources/androidstudio/app/src/main/java/com/nativephp/mobile/bridge/LaravelEnvironment.kt:799` — Android `LARAVEL_STORAGE_PATH` env var injection
- `resources/androidstudio/app/src/main/cpp/include/php/main/build-defs.h:17` — Android PHP 8.4.5 configure flags (`--enable-json`, etc.)
- `resources/androidstudio/app/src/main/cpp/include/php/main/php_version.h` — `PHP_VERSION "8.4.5"`
- `composer.json:26-27` — `mobile-air` declares `ext-dom` and `ext-simplexml` as runtime requirements, confirming those extensions are bundled in the iOS runtime

</details>

## Switching to a real database

When your project is ready for a production database, change `DB_CONNECTION` in your `.env`, set your credentials, and run:

```bash
php artisan migrate:fresh
```

Your models, migrations, and queries remain unchanged. No driver-specific code to remove.

## Limitations

- No arbitrary joins or subqueries. Eloquent relationships and `whereHas`/`has`/`withCount` are resolved internally as sequential lookups
- No transactions
- No concurrency support — not suitable for multi-process or high-frequency write scenarios
- Laravel 13+ exclusive

## When to use this driver

**Good fit:** local development, testing, MVPs, offline mobile apps (e.g. NativePHP), field data collection, datasets in the low thousands of records.

**Not a good fit:** web backends serving concurrent requests, applications with high-frequency writes, or datasets that grow without a known upper bound.

## Testing

```bash
composer test
```

## Static analysis

```bash
composer analyse
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow.

## License

MIT
