<?php
/**
 * ServiceProvider
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed;

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\Event;

/**
 * ServiceProvider
 */
abstract class ServiceProvider implements ProviderInterface
{
  use GravTrait;
  /**
   * @var \Grav\Common\Data\Data
   */
  protected $config;

  /**
   * @var string
   */
  protected $embedCode = '';

  /**
   * @var array
   */
  protected $attributes;

  /**
   * @var array
   */
  protected $params;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Constructor.
   */
  public function __construct(array $config = [])
  {
    $this->config = new Data($config);
  }

  public function init($embedCode)
  {
    $this->reset();

    // Normalize URL to embed
    $this->embedCode = $this->canonicalize($embedCode);
    $url = $this->parseUrl($embedCode);

    // Get media attributes and object parameters
    $attributes = $this->config->get('media', []);
    $params = array_replace_recursive($this->config->get('params', []), $url['query']);

    // Copy media attributes from object parameters
    $attr_keys = ['width', 'height', 'crop', 'preview', 'responsive'];
    foreach ($attr_keys as $key) {
      if (isset($params[$key])) {
        $attributes[$key] = $params[$key];
        unset($params[$key]);
      }
    }

    // Set media attributes and object parameters
    $this->attributes($attributes);
    $this->params($params);
  }

  public function reset() {
    // Reset values
    $this->embedCode = '';

    $this->attributes([]);
    $this->params([]);
  }

  public function id()
  {
    return $this->embedCode;
  }

  public function slug()
  {
    $slug = strtolower($this->name()) . '://' . $this->id();
    return $slug;
  }

  public function name()
  {
    $name = $this->config->get('name', get_class($this));
    return substr($name, strrpos($name, '\\') + 1);
  }

  public function type()
  {
    return $this->config->get('type', 'unknown');
  }

  public function thumbnail() {
    $thumbnails = $this->config->get('thumbnail', []);
    if (is_string($thumbnails)) {
      $thumbnails = [$thumbnails];
    }

    $url = '';
    foreach ($thumbnails as $thumbnail) {
      $thumbnail = $this->format($thumbnail);
      if (substr(get_headers($thumbnail)[0], -6) == '200 OK') {
        $url = $thumbnail;
        break;
      }
    }

    return $url;
  }

  public function attributes($var = null)
  {
    if ($var !== null) {
      $this->attributes = $var;
    }
    if (!is_array($this->attributes)) {
      $this->attributes = [];
    }
    return $this->attributes;
  }

  public function params($var = null)
  {
    if ($var !== null) {
      $this->params = $var;
    }
    if (!is_array($this->params)) {
      $this->params = [];
    }
    return $this->params;
  }

  /**
   * Return the domain(s) of this media resource
   *
   * @return string
   */
  // public function getDomains()
  // {
  //   return [];
  // }

  public function onTwigTemplateVariables(Event $event)
  {
    $mediaembed = $event['mediaembed'];
    foreach ($this->config->get('assets', []) as $asset) {
      $mediaembed->add($asset);
    }
  }

  /**
   * Convenience wrapper for `echo $ServiceProvider`
   *
   * @return string
   */
  public function __toString() {
    return $this->getEmbedCode();
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  protected function format($string, $params = [])
  {
    $params += [
      '{:id}' => $this->id(),
      '{:name}' => $this->name(),
      '{:url}' => urlencode($this->config->get('website', '')),
    ];

    // Format URL placeholder with params
    $params['{:url}'] = urlencode(str_ireplace(array_keys($params), $params, $params['{:url}']));

    $string = preg_replace_callback('~\{\:oembed(?:\.(?=\w))([\.\w_]+)?\}~i',
      function($match) {
        static $oembed;

        if (is_null($oembed)) {
          $oembed = new Data($this->getOEmbed());
        }

        return $oembed->get($match[1], '');
    }, $string);

    return str_ireplace(array_keys($params), $params, $string);
  }

  protected function parseUrl($url)
  {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return [];
    }

    // Parse URL
    $url = html_entity_decode($url, ENT_COMPAT | ENT_HTML401, 'UTF-8');
    $parts = parse_url($url);
    $parts['url'] = $url;

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
