# SitesketchPHP

**SitesketchPHP** is a lightweight PHP microframework designed for building template-driven websites quickly with clean HTML-based template (`*.tpl`) files.

---

## Features

- **Template Engine (`ContentBuilder`)**: Simple and readable tag-based templating syntax supporting value substitution, nested loop/array blocks, conditional blocks, and evaluation tags.
- **Page Assembly (`PageBuilder`)**: Streamlines creating full HTML documents with automated doctypes, head metadata, responsive stylesheet management, and script tags (including `defer`).
- **Database & Sessions**: Built-in PDO integration via `DatabaseHandlerTrait` and database-backed session management via `DatabaseSessionManager`.
- **Form Generation (`FormBuilder`)**: Generates and processes HTML forms with field population and validation.
- **Asset Processing**: Automated image thumbnail generation (`ThumbnailGenerator`) and slideshow components (`Slideshow`, `SlideController`).
- **Modern Standards**: PSR-4 autoloading, PSR-12 coding standard, and strict typing (`declare(strict_types=1)`).

---

## Requirements

- **PHP**: `^8.1` or higher
- **Extensions (Optional)**:
  - `ext-pdo` — Required when using database-backed sessions or `DatabaseHandlerTrait`
  - `ext-gd` — Required when using `ThumbnailGenerator` for image resizing

---

## Installation

Install via Composer:

```bash
composer require ghbarratt/sitesketchphp
```

Ensure Composer's autoloader is included in your application entry point:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

---

## Quick Start

### 1. Template Rendering with `ContentBuilder`

Create a template file (e.g. `resources/templates/welcome.tpl` or `welcome.tpl`):

```html
<h1>Welcome, ~name~!</h1>
<ul>
~items{
    <li>~item_name~: $~price~</li>
}items~
</ul>
```

Render it with `ContentBuilder`:

```php
use Sitesketch\ContentBuilder;

$cb = new ContentBuilder('welcome.tpl', [
    'name' => 'Alice',
    'items' => [
        ['item_name' => 'Product 1', 'price' => '10.00'],
        ['item_name' => 'Product 2', 'price' => '25.00'],
    ]
]);

echo $cb->getContent();
```

#### Template Tag Syntax

- **Variable Replacement**: `~variable_name~`
- **Loops / Repeated Sections**: `~items{ ... }items~`
- **Conditional (Inverse / Bang) Tags**: `~!logged_in{ <a href="/login">Login</a> }logged_in~`
- **Dynamic Evaluation**: `~eval(date('Y'))eval~`

---

### 2. Full Page Assembly with `PageBuilder`

`PageBuilder` assembles a complete HTML document including doctype, head elements, stylesheets, scripts, and body content:

```php
use Sitesketch\PageBuilder;

$page = new PageBuilder('home');
$page->title = 'Home Page';
$page->setDescription('Welcome to our modern website');
$page->addStylesheet('/css/main.css');
$page->addScript('/js/app.js');

// Output full HTML page
echo $page->getPageContent();
```

---

## Directory Structure

```text
sitesketchphp/
├── composer.json               # Package definition and autoloading
├── resources/
│   ├── js/                     # Standalone JavaScript utility scripts
│   └── templates/              # Core framework templates (body, dtd, head, page)
└── src/
    ├── ContentBuilder.php      # Core templating engine
    ├── DatabaseSessionManager.php
    ├── FormBuilder.php         # Form generator and validator
    ├── PageBuilder.php         # HTML page assembler
    ├── SlideController.php
    ├── Slideshow.php
    ├── ThumbnailGenerator.php  # Image resizing and thumbnailing
    ├── UserManager.php         # User management utilities
    ├── helpers.php             # Procedural helper functions
    └── Traits/
        └── DatabaseHandlerTrait.php
```

---

## Coding Standards

SitesketchPHP adheres strictly to **PSR-12** formatting and **PSR-4** namespace autoloading (`Sitesketch\`). Code style can be verified using PHP_CodeSniffer:

```bash
vendor/bin/phpcs src/
```

---

## License

SitesketchPHP is open-source software licensed under the [MIT License](LICENSE).
