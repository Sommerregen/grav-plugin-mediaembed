<?php
/**
 * Service
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed;

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use RocketTheme\Toolbox\Event\Event;

/**
 * Service
 */
class Service
{
	/**
   * @var Service
   */
	use GravTrait;

	/** ---------------------------
   * Private/protected properties
   * ----------------------------
   */

  /**
   * @var Grav\Plugin\MediaEmbed\ServiceProvider
   */
  protected $services = [];

  /**
   * @var array
   */
  protected $domains;

  /** -------------
   * Public methods
   * --------------
   */

  public function __construct()
  {
    // Fire event
  	self::getGrav()->fireEvent('onMediaEmbed', new Event(['service' => $this]));
  }

  public function call($method, $params = [])
  {
    $result = [];
    foreach ($this->services as $key => $service) {
      if (method_exists($service['provider'], $method)) {
        $data = call_user_method_array([$service['provider'], $method], $params);
        if ($data) {
          $result[] = $data;
        }
      }
    }

    return $result;
  }

  public function match($url, $embedCode = null)
  {
    $embed_key = 'embed';
    if ($embedCode && (strtolower($embedCode) !== $embed_key)) {
      return false;
    }

    $parts = $this->parseUrl($url);
    if ($parts['host'] == $embed_key) {
      return false;
    }

    return isset($this->domains[$parts['host']]);
  }

  public function embed($embedCode, $options = [])
  {
    if (!$this->match($embedCode)) {
      throw new \Exception('Unknown embed code "' . htmlspecialchars($embedCode) . '".');
    }

    // Extract domain from embed code
    $domain = strtolower($this->parseUrl($embedCode)['host']);
    $key = $this->domains[$domain];

    // Call OEmbed service provider
    $provider = $this->services[$key]['provider'];
    $provider->init($embedCode, $options);

    // Return initialized provider
    return $provider;

    // // Get type of media
    // $type = ucfirst($provider->type());

    // // Get the default properties of the response class
    // $class = __NAMESPACE__ . "\\Response\\{$type}Response";

    // // Repopulate properties
    // $vars = [
    //   'raw' => $embedCode,
    //   'options' => $provider->attributes(),
    //   'url' => $provider->getEmbedCode(,
    // ] + get_class_vars($class);

    // // Enrich properties with media resource informations
    // foreach ($vars as $key => $value) {
    //   if (!$value) {
    //     $vars[$key] = $provider->{$key}();
    //   }
    // }

    // // Create response
    // $response = new $class($vars);

    // return [$provider, $response];

    //   try {
    //     // Call ServiceProvider
    //     $provider->init($embedCode);

    //     $embed += array(
    //       // Get unique id of video
    //       'id' => $provider->canonicalize($embedCode),

    //       // Templates are stored in the templates/partials folder
    //       'assets' => $provider->getAssets(),
    //       'template' => $provider->getTemplatePaths(),
    //       'variables' => $provider->getTemplateVariables(),

    //       // Store embed status of ServiceProvider call
    //       'success' => true,
    //       'message' => '',
    //     );
    //   } catch (\Exception $e) {
    //     $embed['success'] = false;
    //     $embed['message'] = $e->getMessage();
    //   }
    // }

    // return $embed;
  }

  public function getDomains()
  {
    $allDomains = [];
    foreach ($this->services as $key => $service) {
      $allDomains[] = $service['domains'];
    }

    // Flatten multidimensional array of domains
    $domains = [];
    array_walk($allDomains, function($domain) use (&$domains) {
      $domains[] = $domain;
    });

    // Return unique array of domains
    return array_unique($domains);
  }

  public function getProviders()
  {
    // Get providers sorted by priority
    $classes = $this->collectProviders(function($key, $service) {
      return true;
    }, true, 'class');

    // Replace names with provider classes
  	$providers = [];
  	foreach ($classes as $class) {
  		$name = substr($class, strrpos($class, '\\') + 1);
      $provider = $this->services[$class]['provider'];

  		if ( isset($providers[$name]) ) {
  			$providers[$name] = array($providers[$name], $provider);
  		} else {
  			$providers[$name][] = $provider;
  		}
  	}

  	return $providers;
  }

  public function getProviderByName($name, $all = false)
  {
    $providers = $this->collectProviders(
      function($key, $service) use ($name) {
        return preg_match("~^$name$~i", $service['name']);
    }, $all);

    return $providers;
  }

  public function getProviderByDomain($domain, $all = false)
  {
    $providers = $this->collectProviders(
      function($key, $service) use ($domain) {
        return in_array($domain, $service['domains']);
    }, $all);

  	return $providers;
  }

	public function register($provider, $priority = 0)
  {
		if ($provider instanceof \Grav\Plugin\MediaEmbed\OEmbed\OEmbed) {
      $key = md5(spl_object_hash($provider));
      $domains = $provider->getDomains();

			$this->services[$key] = array(
				'priority' => $priority,
				'domains'  => $domains,
				'provider' => $provider,
        'name'     => $provider->name(),
        'class'    => $key,
			);

      foreach ($domains as $domain) {
        if (!isset($this->domains[$domain]) || ($priority > $this->services[$domain]['priority'])) {
          $this->domains[$domain] = $key;
        }
      }
		}
	}

	public function unregister($provider)
  {
		if ($provider instanceof \Grav\Plugin\MediaEmbed\OEmbed\OEmbed) {
			$key = md5(spl_object_hash($provider));
			if (isset($this->services[$key])) {
        $domains = $this->services[$key]['domains'];
				unset($this->services[$key]);

        // Unset and repopulate domain keys, if possible
        foreach ($domains as $domain) {
          if ($provider = $this->getProviderByDomain($domain)) {
            $this->domains[$domain] = get_class($provider);
          } else {
            unset($this->domains[$domain]);
          }
        }
			}
		}
	}

  protected function collectProviders($callback, $all, $id = 'provider')
  {
    $services = [];
    foreach ($this->services as $key => $service) {
      if ($callback($key, $service)) {
        $services[] = $service;
      }
    }

    // Sort providers based on priority
    uasort($services, function ($a, $b) {
      // Priority is first sort criterion
      $cmp = $a['priority'] - $b['priority'];

      // Text string is second criterion (in case of two equal priorities)
      if ( $cmp == 0 ) {
        $cmp = strnatcmp($a['name'], $b['name']);
      }

      return $cmp;
    });

    // Strip additional service informations
    $providers = array_map(function($service) use ($id) {
      return $service[$id];
    }, $services);

    if (count($providers)) {
      // Return providers
      return ($all ? $providers : $providers[0]);
    }

    return [];
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  protected function parseUrl($url)
  {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return [];
    }

    // Parse URL
    $url = html_entity_decode($url, ENT_COMPAT | ENT_HTML401, 'UTF-8');
    $parts = parse_url($url);
    $parts['url'] = $url;
    $parts['host'] = preg_replace("/^www\./", '', $parts['host']);

    // Get top-level domain from URL
    $parts['domain'] = isset($parts['host']) ? $parts['host'] : '';
    if ( preg_match('~(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$~i', $parts['domain'], $match) ) {
      $parts['domain'] = $match['domain'];
    }

    if (isset($parts['query'])) {
      parse_str(urldecode($parts['query']), $parts['query']);
    }
    $parts['query'] = [];

    return $parts;
  }
}
