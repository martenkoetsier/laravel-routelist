# laravel-routelist

Alternative to `route:list` console command with more emphasis on route names, shortened controller names, more compact
middleware view and other small tweaks.

As of Laravel 9, the route:list command output has changed. In this new output, route names are not alligned and
printed in a dark color. Also, the default output does not list middleware. This package replaces the existing console
command. The new output will:

- vertically align the route names, immediately after the route uri's
- highlight the route names
- replace the fixed string `Controller` in the route action by a single character `©` to save horizontal space
- replace the `@` sign in the route action by `∷` (one glyph)
- list the middlewares by default
- shorten the middlewares into their aliases as known via the App\Http\Kernel class (unless the `-v` option is added)
- place middleware on as few lines as possible
- make use of the full available horizontal space
- replace the fully dotted fillers by fewer dots, neatly alligned

## Installation

```
composer require martenkoetsier/laravel-routelist
```

The automatic package discovery will add the service provider to the `config/app.php` file.

## Usage

Usage is as with the original route list command in Laravel:

```
php artisan route:list
```

Adding verbosity (`-v`) will not shorten the middleware names into their applicable aliases and instead list the full
middleware class names.
