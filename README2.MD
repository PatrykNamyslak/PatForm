# PatForm - Schema-Driven Form Generation

Stop writing forms twice. PatForm reads your database schema and generates HTML forms automatically.

## The Problem

```php
// You define your schema once...
CREATE TABLE products (
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('active','draft','archived')
);

// Then write it AGAIN in PHP
$form->add('name', TextType::class, [
    'required' => true,
    'max_length' => 100,
    'label' => 'Product Name'
]);
$form->add('price', NumberType::class, [
    'required' => true,
    'scale' => 2,
    'label' => 'Price'
]);
$form->add('description', TextareaType::class, [
    'required' => false,
    'label' => 'Description'
]);
$form->add('status', ChoiceType::class, [
    'choices' => ['Active', 'Draft', 'Archived'],
    'label' => 'Status'
]);
// 20+ lines of duplication...
```

## The PatForm Solution

```php
// That's it. One line.
$form = new Form($db, 'products');
$form->render('Add Product');
```

**PatForm automatically:**
- ✅ Detects field types from database columns
- ✅ Applies validation (required, max length, etc.)
- ✅ Generates dropdowns from ENUM types
- ✅ Adds CSRF protection
- ✅ Supports HTMX for modern UX
- ✅ Works with 9+ databases

## Installation

```bash
composer require patryknamyslak/patform
```

## Quick Start

```php
use PatrykNamyslak\FormBuilder\Form;
use PatrykNamyslak\Patbase\Patbase;
use PatrykNamyslak\Patbase\Enums\DatabaseDriverType;

// Connect to database
$db = new Patbase(
    driver: DatabaseDriverType::MYSQL,
    database: 'myapp',
    username: 'root',
    password: 'secret'
);

// Generate form from 'users' table
$form = new Form($db, 'users');
$form->action('/register')
     ->method(RequestMethod::POST)
     ->render('Register');
```

**Output:**
```html
<h2>Register</h2>
<form action="/register" method="POST">
    <input type="hidden" name="csrf_token" value="...">
    
    <label>Email</label>
    <input type="email" name="email" maxlength="255" required>
    
    <label>Username</label>
    <input type="text" name="username" maxlength="100" required>
    
    <label>Password</label>
    <input type="password" name="password" maxlength="255" required>
    
    <button type="submit">Submit</button>
</form>
```

## Schema-to-Input Mapping

PatForm reads your database schema and automatically generates the right input types:

| Database Type | Generated Input | Example |
|---------------|----------------|---------|
| `VARCHAR(n)` | `<input type="text" maxlength="n">` | Text field |
| `TEXT` / `LONGTEXT` | `<textarea>` | Large text area |
| `INT` / `BIGINT` | `<input type="number">` | Number input |
| `DECIMAL(p,s)` | `<input type="number" step="0.01">` | Decimal input |
| `ENUM('a','b')` | `<select><option>a</option>...</select>` | Dropdown |
| `TIMESTAMP` / `DATE` | `<input type="date">` | Date picker |
| `TINYINT(1)` | `<input type="radio">` | Yes/No |

**Validation is automatic:**
- `NOT NULL` columns → `required` attribute
- `VARCHAR(100)` → `maxlength="100"`
- `ENUM` values → dropdown options
- Default values → pre-filled

## Examples

### Basic CRUD Form
```php
// Create table
CREATE TABLE posts (
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('draft','published') DEFAULT 'draft',
    published_at TIMESTAMP NULL
);

// Generate form (that's it!)
$form = new Form($db, 'posts');
$form->render('Create Post');
```

### With HTMX (SPA-like UX)
```php
$form = new Form($db, 'users');
$form->action('/api/users')
     ->method(RequestMethod::POST)
     ->htmx()  // Enable HTMX
     ->render('Register');
```

**Result:** Form submits via AJAX, no page reload!

### Custom Field Selection
```php
// Only show specific fields
$form = new Form($db, 'users');
$form->onlyUse(['email', 'username', 'password'])
     ->render('Quick Register');
```

### Handle Form Submission
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = new Form($db, 'users');
    $form->submit($_POST);  // Validates + inserts
}
```

## Supported Databases

- ✅ MySQL / MariaDB
- ✅ PostgreSQL
- ✅ SQLite
- ✅ SQL Server
- ✅ Oracle
- ✅ And more...

Works with any database supported by [Patbase](https://github.com/PatrykNamyslak/Patbase).

## Why PatForm?

### vs Manual HTML Forms
| | Manual HTML | PatForm |
|---|---|---|
| **Lines of code** | 50-100+ | 3 |
| **Maintenance** | Update 2 places | Update database only |
| **Validation** | Manual | Automatic from schema |
| **CSRF protection** | Manual implementation | Built-in |
| **Type safety** | None | From database types |

### vs Laravel Forms
| | Laravel FormBuilder | PatForm |
|---|---|---|
| **Configuration** | 20-40 lines per form | 1 line |
| **Schema sync** | Manual | Automatic |
| **Framework** | Laravel-locked | Any PHP project |
| **Learning curve** | 2-3 days | 5 minutes |

### vs Symfony Forms
| | Symfony Forms | PatForm |
|---|---|---|
| **Configuration** | 30-50 lines + FormType class | 1 line |
| **Flexibility** | High (but complex) | High (but simple) |
| **Framework** | Symfony-locked | Any PHP project |
| **Setup time** | Hours | Minutes |

## Real-World Use Cases

### ✅ Admin Panels
Generate CRUD interfaces for any table instantly.

### ✅ Rapid Prototyping
Build working forms in seconds, iterate fast.

### ✅ Multi-Tenant Apps
Support custom schemas without code changes.

### ✅ Dynamic Forms
Users define fields in DB, forms update automatically.

### ✅ API-Driven Apps
Generate forms on frontend from schema endpoints.

## Advanced Features

### HTMX Integration
```php
$form->htmx(
    swapMode: HtmxSwapMode::innerHTML,
    responseTarget: '#results'
);
```

### Custom Validation Hooks
```php
class MyForm extends Form {
    protected function beforeSubmit(array $data) {
        // Custom validation
    }
    
    protected function afterSubmit(array $data) {
        // Send email, log, etc.
    }
}
```

### Fluent API
```php
$form = new Form($db, 'users');
$form->action('/register')
     ->method(RequestMethod::POST)
     ->wrapFields()  // Add div wrappers
     ->submitButtonText('Create Account')
     ->htmx()
     ->render('Sign Up');
```

## Dependencies

- PHP 8.4+
- [patryknamyslak/patbase](https://github.com/PatrykNamyslak/Patbase) - Database abstraction

## License

MIT

## Author

**Patryk Namyslak** - [Website](https://patryknamyslak.dev) · [GitHub](https://github.com/PatrykNamyslak)

---

## FAQ

**Q: Does this work with existing projects?**  
A: Yes! Just pass your existing database connection and table name.

**Q: Can I customize the generated HTML?**  
A: Yes, extend the `Form` class and override rendering methods.

**Q: What about complex forms with relationships?**  
A: Use `onlyUse()` to select fields or create multiple forms.

**Q: Is this secure?**  
A: Yes. Built-in CSRF protection, parameterized queries, validation from schema.

**Q: Can I use it without a framework?**  
A: Absolutely! It's framework-agnostic by design.