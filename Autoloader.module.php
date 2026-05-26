<?php

/**
 *  Autoloader
 *  @author Ivan Milincic <kreativan.dev@gmail.com>
 *  @link https://kreativan.dev
 */

namespace ProcessWire;

class Autoloader extends WireData implements Module {

  public static function getModuleInfo() {
    return array(
      'title' => 'Autoloader',
      'version' => 100,
      'summary' => 'Autoloader for ProcessWire hooks, actions and classes.',
      'icon' => 'connectdevelop',
      'author' => "Ivan Milincic",
      "href" => "https://kreativan.dev",
      'singular' => true,
      'autoload' => true,
      'requires' => ['ProcessWire>=3.0.0'],
    );
  }

  public function __construct() {
    parent::__construct();
  }

  public function init() {
    /**
     * Clear autoloader cache when modules are refreshed
     */
    $this->addHookAfter('Modules::refresh', function () {
      $this->wire()->cache->delete('autoloader_hook_folders');
      $this->wire()->cache->delete('autoloader_action_modules');
    });

    /**
     * Autoloader Hooks
     * Scans all site/modules/ for /hooks/ folders and includes all PHP files.
     * Cached until modules are refreshed.
     */
    $hookFolders = $this->wire()->cache->get('autoloader_hook_folders', WireCache::expireNever, function () {
      $folders = [];
      $modulesDir = $this->wire()->config->paths->siteModules;
      foreach (glob($modulesDir . '*/autoload/hooks/', GLOB_ONLYDIR) as $hooksDir) {
        $folders[] = $hooksDir;
      }
      return $folders;
    });
    foreach ($hookFolders as $folder) {
      $this->loadHooksFolder($folder);
    }

    /**
     * Autoloader Actions
     * Scans all site/modules/ for /actions/ folders.
     * Includes a file only when ?module-name=action-name GET variable is present.
     * The GET key is the module name converted to kebab-case (e.g. MyModule -> my-module).
     * Cached until modules are refreshed.
     */
    if ($this->adminHelper->isAdmin()) {
      $actionModules = $this->wire()->cache->get('autoloader_action_modules', WireCache::expireNever, function () {
        $map = [];
        $modulesDir = $this->wire()->config->paths->siteModules;
        foreach (glob($modulesDir . '*/autoload/actions/', GLOB_ONLYDIR) as $actionsDir) {
          $moduleName = basename(dirname(dirname($actionsDir)));
          $getKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
          $map[$getKey] = $actionsDir;
        }
        return $map;
      });
      $rootPath = $this->wire()->config->paths->root;
      foreach ($actionModules as $getKey => $actionsDir) {
        $relDir = '/' . ltrim(str_replace($rootPath, '', $actionsDir), '/');
        $this->loadActions($getKey, $relDir);
      }
    }
  }

  public function ready() {
  }

  public function path() {
    return $this->config->paths->siteModules . $this->className() . "/";
  }

  public function url() {
    return $this->config->urls->siteModules . $this->className() . "/";
  }

  /**
   * Include all PHP files from a specified folder
   * @param string $folder - folder path
   * @param bool $recursive - include subfolders (default: true)
   * @throws WireException if folder does not exist
   */
  public function loadFolder($folder, $recursive = true) {
    $path = rtrim(str_replace('//', '/', $folder), '/');
    $path = $this->config->paths->root . $path;
    if (!is_dir($path)) {
      throw new WireException("Folder not found: {$path}");
    }
    if ($recursive) {
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
      );
    } else {
      $iterator = new \DirectoryIterator($path);
    }
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), '_') !== 0) {
        include_once($file->getPathname());
      }
    }
  }

  /**
   * Include all PHP files from an absolute hooks folder path, recursively.
   * Skips files whose names start with a dot.
   * @param string $absolutePath - absolute filesystem path to the hooks folder
   */
  public function loadHooksFolder(string $absolutePath): void {
    $path = rtrim($absolutePath, '/');
    if (!is_dir($path)) return;

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), '.') !== 0) {
        include_once($file->getPathname());
      }
    }
  }

  /**
   * Autoload Actions
   * Include a file from a module's /actions/ folder based on a GET variable.
   * The GET key is the module name in kebab-case (e.g. MyModule -> ?my-module=action-name).
   *
   * @param string $GET_NAME  - GET variable name (module kebab-case name)
   * @param string $folder    - site-relative path to the actions folder
   *
   * @example ?my-module=my-action
   * includes: /site/modules/MyModule/autoload/actions/my-action.php
   *
   * @example ?my-module=subfolder/my-action
   * includes: /site/modules/MyModule/autoload/actions/subfolder/my-action.php
   */
  public function loadActions($GET_NAME, $folder = null) {
    $action = $this->sanitizer->text($this->input->get->{$GET_NAME});

    // Prevent directory traversal and leading slash
    $action = ltrim($action, '/');
    if (!$action) return;

    if (strpos($action, '..') !== false) {
      $this->error("Invalid action name.");
      return;
    }

    $file = $this->config->paths->root . $folder . $action . ".php";
    $file = str_replace('//', '/', $file); // Normalize path
    $file = str_replace('\\', '/', $file); // Normalize backslashes for Windows paths

    if (file_exists($file)) {
      $this->files->include($file, [
        'action' => $action,
      ]);
    } else {
      $this->error("$action action file not found");
    }
  }
}
