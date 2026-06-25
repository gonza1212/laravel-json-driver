# laravel-json-driver

![CI](https://github.com/gonza1212/laravel-json-driver/actions/workflows/ci.yml/badge.svg)
[![Latest Release](https://img.shields.io/github/v/release/gonza1212/laravel-json-driver?color=32a852&logo=laravel)](https://github.com/gonza1212/laravel-json-driver/releases)

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

Add the connection entry in `config/database.php`:

```php
'connections' => [
    'json' => [
        'driver'   => 'json',
        'database' => env('DB_DATABASE', storage_path('app/json-db')),
        'prefix'   => '',
    ],
    // ...
],
```

The driver registers via auto-discovery. No changes to `config/app.php` required.

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
