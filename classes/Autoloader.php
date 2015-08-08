<?php
/**
 * Autoloader
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed;

/**
 * Autoloader
 */
class Autoloader
{
	protected $routes = [];

	public function __construct($routes = [])
	{

		// Set routes for autoloading
    if (!is_array($routes) || count($routes) == 0) {
      $routes = [__NAMESPACE__ => __DIR__];
    }

    $this->route($routes);
	}

  public function route($var = null, $reset = true)
  {
    if ($var !== null && is_array($var)) {
      if ($reset) {
         $this->routes = [];
      }

      // Setup routes
      foreach ($var as $prefix => $path) {
        if (false !== strrpos($prefix, '\\')) {
          // Prefix is a namespaced path
          $prefix = rtrim($prefix, '_\\') . '\\';
        } else {
          // Prefix contain underscores
          $prefix = rtrim($prefix, '_') . '_';
        }

        $this->routes[$prefix] = rtrim($path, '/\\') . '/';
      }
    }

    return $this->routes;
  }

	/**
   * Autoload classes
   *
   * @param  string $class Class name
   *
   * @return mixed  false  FALSE if unable to load $class; Class name if
   *                       $class is successfully loaded
   */
  public function autoload($class)
  {
    foreach ($this->routes as $prefix => $path) {
      // Only load classes of MediaEmbed plugin
      if (false !== strpos($class, $prefix)) {
        // Remove prefix from class
        $class = substr($class, strlen($prefix));

        // Replace namespace tokens to directory separators
        $file = $path . preg_replace('#\\\|_(?!.+\\\)#', '/', $class) . '.php';

        // Load class
        if (stream_resolve_include_path($file)) {
          return include_once($file);
        }

        return false;
      }
    }

  	return false;
  }

	/**
   * Registers this instance as an autoloader
   *
   * @param bool $prepend Whether to prepend the autoloader or not
   */
	public function register($prepend = false)
	{
		spl_autoload_register(array($this, 'autoload'), false, $prepend);
  }

  /**
   * Unregisters this instance as an autoloader
   */
  public function unregister()
  {
  	spl_autoload_unregister(array($this, 'autoload'));
  }
}
