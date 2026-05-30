# Autoloader

Auto-includes PHP files from `autoload/hooks/` and `autoload/actions/` directories inside any site module. Eliminates manual `require_once` in `init()` and `ready()` files. The discovery result is cached until **Modules → Refresh** is run.

## API variable

None. Effects are applied during `init()`.

## How it works

### Hook autoloading

On `init()`, Autoloader scans all `site/modules/*/autoload/hooks/` directories and `require_once`s every `.php` file inside them (recursively). Files starting with `_` or `.` are skipped.

**Place hook files here:**
```
site/modules/MyModule/autoload/hooks/my-hooks.php
site/modules/MyModule/autoload/hooks/payments/stripe.php  # subdirs supported
```

### Action autoloading

Actions are admin-request-only. A file is included only when a matching GET parameter is present:

```
GET /admin/page/?my-module=do-something
→ includes site/modules/MyModule/autoload/actions/do-something.php
```

The GET key is the module name converted to kebab-case (`MyModule` → `my-module`). Actions are only loaded inside admin requests.

## Cache keys

| Key                        | Content                                        | Cleared on         |
|----------------------------|------------------------------------------------|--------------------|
| `autoloader_hook_folders`  | Array of absolute paths to `autoload/hooks/`  | Modules → Refresh  |
| `autoloader_action_modules`| Map of `get-key → absolute actions dir path`  | Modules → Refresh  |

After adding a new `autoload/hooks/` or `autoload/actions/` directory, run **Modules → Refresh** to pick it up.

## Public methods

```php
$autoloader = wire('modules')->get('Autoloader');

// Include all PHP files from a site-relative path (recursive by default)
$autoloader->loadFolder('/site/mymodule/partials/', recursive: true);

// Include all PHP files from an absolute hooks folder path
$autoloader->loadHooksFolder('/absolute/path/to/hooks/');
```

## Conventions

- Hook files must be self-contained: include any `use` statements, and always access the ProcessWire API via `wire()` rather than relying on injected variables.
- Action files execute in admin context — `$page`, `$input`, and all PW API vars are available.
- Prefix a file with `_` to prevent Autoloader from including it (useful for shared include files).

## Notes

- Requires `AdminHelper` module (uses `$this->adminHelper->isAdmin()` to gate action loading).
- Hook files are included at `init()` time — they must not depend on modules that load after `init()`. For hooks that need `ready()` context, attach them inside `$this->wire()->addHookAfter('ProcessWire::ready', ...)` inside the hook file.
- The scan and cache happen on every request until invalidated; the filesystem is only read once per cache period.
