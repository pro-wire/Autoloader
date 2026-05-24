# Autoloader

Autoloader is a ProcessWire module that automatically discovers and includes PHP hook files and action files from any module in `site/modules/`. It also exposes manual helpers for loading arbitrary files.

**Requires:** ProcessWire >= 3.0.0, AdminHelper  
**Autoload:** `true` (all requests)  
**Singular:** yes

---

## Global variable

```php
wire('autoloader')         // anywhere
$this->wire('autoloader')  // inside module methods
$autoloader                // when in ProcessWire template / hook scope
```

---

## Automatic hooks discovery

On `init()`, Autoloader scans every module in `site/modules/` for an `autoload/hooks/` subfolder and recursively includes all `.php` files found there. No registration or configuration is needed.

```
site/modules/MyModule/autoload/hooks/my-hook.php   ← included automatically
site/modules/MyModule/autoload/hooks/sub/more.php  ← subdirectories included too
site/modules/MyModule/autoload/hooks/.draft.php    ← skipped (dot-prefix)
```

The folder list is cached with `WireCache::expireNever` and cleared automatically when **Admin → Modules → Refresh** is triggered.

---

## Automatic actions discovery

Autoloader scans every module for an `autoload/actions/` subfolder. A file is included only when a GET parameter whose name matches the **module name converted to kebab-case** is present in the request.

| Module directory name | GET key |
|---|---|
| `MyModule` | `my-module` |
| `AdminHelper` | `admin-helper` |
| `ProjectTracking` | `project-tracking` |

```
?admin-helper=pages/publish    →  AdminHelper/autoload/actions/pages/publish.php
?my-module=create-invoice      →  MyModule/autoload/actions/create-invoice.php
?my-module=import/products     →  MyModule/autoload/actions/import/products.php
```

The included file receives these variables in scope:

| Variable | Type | Description |
|---|---|---|
| `$action` | `string` | The sanitized action name from the GET parameter |

All standard ProcessWire API variables (`$pages`, `$input`, `$sanitizer`, etc.) and any registered wire variables (e.g. `$adminHelper`, `$nautilus`) are also available because the file is included within the ProcessWire context.

For input validation inside action files, use `$adminHelper->valitron()` — see [AdminHelper — Validation](../AdminHelper/docs/validation.md).

Directory traversal (`..`) is rejected. The action-module map is cached with `WireCache::expireNever` and cleared on **Modules → Refresh**.

---

## Manual helpers

### `loadHooksFolder(string $absolutePath): void`

Include all `.php` files from an absolute path recursively. Files whose names start with `.` are skipped. Does nothing if the directory does not exist.

```php
$autoloader->loadHooksFolder($config->paths->siteModules . 'MyModule/extra-hooks/');
```

### `loadFolder(string $folder, bool $recursive = true): void`

Include all `.php` files from a **site-relative** path (relative to `$config->paths->root`). Files whose names start with `_` are skipped. Throws `WireException` if the directory does not exist.

```php
// Include all files from /site/modules/MyModule/lib/
$autoloader->loadFolder('/site/modules/MyModule/lib/');

// Non-recursive — top-level files only
$autoloader->loadFolder('/site/modules/MyModule/lib/', false);
```

### `loadActions(string $getKey, string $folder): void`

Manually register a GET-driven action handler for a specific folder (site-relative path). Useful when you need to point to a non-standard location.

```php
$autoloader->loadActions('my-module', '/site/modules/MyModule/autoload/actions/');
// ?my-module=do-something → includes /site/modules/MyModule/autoload/actions/do-something.php
```

---

## Cache invalidation

The two discovery caches (`autoloader_hook_folders` and `autoloader_action_modules`) are deleted whenever `Modules::refresh` fires. To bust them manually:

```php
wire()->cache->delete('autoloader_hook_folders');
wire()->cache->delete('autoloader_action_modules');
```

