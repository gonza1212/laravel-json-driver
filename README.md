# laravel-json-driver

![CI](https://github.com/gonza1212/laravel-json-driver/actions/workflows/ci.yml/badge.svg)
[![Último Release](https://img.shields.io/github/v/release/gonza1212/laravel-json-driver?color=32a852&logo=laravel)](https://github.com/gonza1212/laravel-json-driver/releases)

Driver de base de datos JSON para Laravel 13+. Persistencia local sin base de datos externa.
Ideal para desarrollo rÃ¡pido, prototipado y tests donde no quieras configurar MySQL, PostgreSQL ni SQLite.

## Requisitos

- PHP 8.3+
- Laravel 13+

## Instalacion

```bash
composer require gonza1212/laravel-json-driver --dev
```

## Configuracion

En tu `.env`:

```env
DB_CONNECTION=json
DB_DATABASE=storage/app/json-db
```

Agrega la entrada de conexion en `config/database.php`:

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

El driver se registra via auto-discovery. No requiere cambios en `config/app.php`.

## Uso

### Migraciones

Las migraciones funcionan exactamente igual que con cualquier base de datos SQL:

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

### Operadores de where soportados

`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `in`, `not in`, `is null`, `is not null`, `between`, `not between`, `like`, `whereDate`, `whereYear`, `whereMonth`

### Orden, limite y offset

```php
$posts = Post::where('activo', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->offset(0)
    ->get();
```

### Factories y seeders

```php
Post::factory()->count(10)->create();
```

```bash
php artisan db:seed
```

### Relaciones Eloquent

Las relaciones nativas funcionan out-of-the-box, sin trait ni clase base custom:

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
Autor::withCount('libros')->get();  // agrega columna libros_count

// withPivot
$libro->generos()->attach($genero->id, ['orden' => 1, 'fecha_agregado' => now()]);
$libro->generos()->wherePivot('orden', 1)->first();  // $genero->pivot->orden === 1
```

Las migraciones declaran FKs con la sintaxis estándar de Laravel, y el driver las respeta al eliminar:

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

- `restrictOnDelete()` (default) → `RuntimeException` si intentas borrar un padre con hijos
- `cascadeOnDelete()` → borra las filas relacionadas recursivamente antes de borrar el padre

## Limitaciones

- Sin joins ni subqueries arbitrarios. Las relaciones Eloquent y los patrones `whereHas`/`has`/`withCount` se resuelven internamente como lookups secuenciales
- Sin transacciones
- Sin soporte de concurrencia (escrituras secuenciales)
- No apto para entornos de produccion
- Laravel 13+ exclusivo

## Testing

```bash
composer test
```

## Analisis estatico

```bash
composer analyse
```

## Contribuir

Ver [CONTRIBUTING.md](CONTRIBUTING.md) para el flujo de trabajo completo.

## Licencia

MIT
