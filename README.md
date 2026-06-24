# laravel-json-driver

![CI](https://github.com/gonza1212/laravel-json-driver/actions/workflows/ci.yml/badge.svg)
[![Latest Release](https://img.shields.io/github/v/release/gonza1212/laravel-json-driver?color=32a852&logo=laravel)](https://github.com/gonza1212/laravel-json-driver/releases)

JSON database driver for Laravel 13+. Local persistence without an external database.
Ideal for rapid development, prototyping, and tests where you don't want to configure MySQL, PostgreSQL, or SQLite.

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
        'driver' => 'json',
        'database' => env('DB_DATABASE', storage_path('app/json-db')),
        'prefix' => '',
    ],
    // ...
],
```

The driver registers via auto-discovery. No changes to `config/app.php` required.

## Usage

### Migrations

Migrations work exactly the same as with any SQL database:

```php
Schema::create('notas', function (Blueprint $table) {
    $table->id();
    $table->string('titulo');
    $table->timestamps();
});
```

```bash
php artisan migrate
php artisan migrate:rollback
```

### Eloquent

```php
$nota = Nota::create(['titulo' => 'hola mundo']);
$nota = Nota::find(1);
$notas = Nota::all();
$nota->update(['titulo' => 'editado']);
$nota->delete();
```

### Supported where operators

`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `in`, `not in`, `is null`, `is not null`, `between`, `not between`, `like`, `whereDate`, `whereYear`, `whereMonth`

### Order, limit, and offset

```php
$posts = Post::where('activo', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(0)
    ->get();
```

### Factories and seeders

```php
Post::factory()->count(10)->create();
```

```bash
php artisan db:seed
```

### Eloquent relationships

Native relationships work out-of-the-box, without a trait or custom base class:

```php
class Autor extends Model { /* ... */ use HasFactory; public function libros(): HasMany { return $this->hasMany(Libro::class); } }
class Libro extends Model { /* ... */ public function autor(): BelongsTo { return $this->belongsTo(Autor::class); } }
class Libro extends Model { /* ... */ public function generos(): BelongsToMany { return $this->belongsToMany(Genero::class, 'genero_libro')->withPivot(['orden', 'fecha_agregado']); } }

// Lazy loading
$libro->autor;
$autor->libros;

// Eager loading
Autor::with('libros')->get();

// whereHas / has / withCount
Autor::whereHas('libros', fn($q) => $q->where('titulo', 'like', 'F%'))->get();
Autor::has('libros')->get();
Autor::withCount('libros')->get();  // adds libros_count column

// withPivot
$libro->generos()->attach($genero->id, ['orden' => 1, 'fecha_agregado' => now()]);
$libro->generos()->wherePivot('orden', 1)->first();  // $genero->pivot->orden === 1
```

Migrations declare FKs with standard Laravel syntax, and the driver respects them on delete:

```php
Schema::create('libros', function (Blueprint $table) {
    $table->id();
    $table->foreignId('autor_id')->constrained('autores')->restrictOnDelete();
});

Schema::create('genero_libro', function (Blueprint $table) {
    $table->foreignId('genero_id')->constrained('generos')->restrictOnDelete();
    $table->foreignId('libro_id')->constrained('libros')->cascadeOnDelete();
});
```

- `restrictOnDelete()` (default) → `RuntimeException` if you try to delete a parent with children
- `cascadeOnDelete()` → recursively deletes related rows before deleting the parent

## Limitations

- No arbitrary joins or subqueries. Eloquent relationships and `whereHas`/`has`/`withCount` patterns are resolved internally as sequential lookups
- No transactions
- No concurrency support (sequential writes)
- Not suitable for production environments
- Laravel 13+ exclusive

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
