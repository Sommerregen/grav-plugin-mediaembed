<?php
/**
 * OEmbed
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

use Grav\Common\GravTrait;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\Event;

/**
 * OEmbed
 */
class OEmbed implements OEmbedInterface
{
  use GravTrait;

  /**
   * @var \Grav\Common\Data\Data
   */
  protected $base_config;

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

  /**
   * @var array
   */
  protected $oembed;

  protected $protocol;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Constructor.
   */
  public function __construct(array $config = [])
  {
    $this->base_config = $this->config = new Data($config);

    $schemes = $this->base_config->get('schemes', []);
    if (!is_array($schemes)) {
      $schemes = [$schemes];
    }

    foreach ($schemes as $index => $scheme) {
      $scheme = preg_quote($scheme);

      $schemes[$index] = preg_replace_callback('~((?:\\\\\*){1,2})(.[^\\\\]?|$)~',
        function($match) {
          // Remove control characters
          $separator = preg_replace('~[^\p{L}]~i', '', $match[2]);
          $star = strlen(str_replace('\\', '', $match[1]));

          if (ctype_alnum($separator)) {
            $replace = '.*?';
          } else {
            $separator = (strlen($separator) == 0) ? substr($match[2], -1) : $separator;
            $replace = (strlen($match[2]) > 0) ? "[^$separator ]+" : '[^\"\&\?\. ]+';
          }

          // Wrap one star result in parenthesis
          $replace = ($star > 1) ? $replace : "($replace)";
          return $replace . $match[2];
      }, $scheme);
    }
    $this->base_config->set('schemes', $schemes);
  }

  public function init($embedCode, $config = [])
  {
    $this->reset();

    // Normalize URL to embed
    $url = $this->parseUrl($embedCode);
    $this->embedCode = $this->canonicalize($embedCode);
    $this->oembed = new Data((array) $this->getOEmbed());

    // Get media attributes and object parameters
    $attributes = [
      'width'=> $this->oembed->get('width', 0),
      'height' => $this->oembed->get('height', 0),
      'protocol' => ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://",
    ];

    // $this->config->merge($config);
    // $attributes = $this->config->get('media', []);
    $params = array_replace_recursive($this->config->get('params', []), $url['query']);

    // Copy media attributes from object parameters
    $attr_keys = ['width', 'height', 'adjust', 'preview', 'responsive'];
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

  public function canonicalize($embedCode)
  {
    $schemes = $this->config->get('schemes', []);
    foreach ($schemes as $scheme) {
      preg_match("~$scheme~i", $embedCode, $matches);
      if ($matches && $this->validId(end($matches))) {
        return end($matches);
      }
    }
  }

  /**
   * Check if a media id is valid.
   *
   * @param  string $id Id to check against the oembed stream.
   *
   * @return boolean    TRUE if id is valid, FALSE otherwise. Throws errors
   *                    on invalid ids.
   */
  protected function validId($id)
  {
    $endpoint = $this->config->get('endpoint', '');
    $endpoint = $this->format($endpoint, ['{:id}' => $id]);

    if (!$id || !$endpoint) {
      return false;
    }

    $response = \Requests::head($endpoint);
    // If a head request fails, try to send a get request
    if ($response->status_code != 200) {
      $response = \Requests::get($endpoint);
    }

    if ( $response->status_code == 401 ) {
      throw new \Exception('Embedding has been disabled for this media.');
    } elseif ( $response->status_code == 404 ) {
      throw new \Exception('The media ID was not found.');
    } elseif ( $response->status_code == 501 ) {
      throw new \Exception('Media informations can not be retrieved.');
    } elseif ( $response->status_code != 200 ) {
      throw new \Exception('The media ID is invalid or the media was deleted.');
    } elseif (!$response->success) {
      $response->throw_for_status();
    }

    return true;
  }

  public function reset()
  {
    // Reset values
    $this->embedCode = '';
    $this->oembed = null;

    $this->attributes([], true);
    $this->params([], true);

    $this->config = new Data($this->base_config->toArray());
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
    $name = get_class($this);
    $name = substr($name, strrpos($name, '\\') + 1);

    if ($this->embedCode) {
      $name = $this->config->get('name', $name);
      if (mb_strlen($name) == 0 && $this->oembed) {
        $this->oembed->get('provider_name', '');
      }
    }

    return $name;
  }

  public function title()
  {
    $title = '';
    if ($this->oembed) {
      $title = $this->oembed->get('title', '');
    }
    return $title;
  }

  public function description()
  {
    $description = '';
    if ($this->oembed) {
      $description = $this->oembed->get('description', '');
    }
    return $description;
  }

  public function url()
  {
    $url = '';
    if ($this->embedCode && $this->oembed) {
      $url = $this->format($this->config->get('url', ''), ['{:url}' => '']);
      if (strlen($url) == 0) {
        $url = $this->oembed->get('url', $url);
      } else {
        $protocol = isset($this->attributes['protocol']) ? $this->attributes['protocol'] : '//';
        $url = $protocol . $url;
      }
    }

    return $url;
  }

  public function website()
  {
    $website = '';
    if ($this->oembed) {
      $website = $this->oembed->get('provider_url', '');
    }
    return $website;
  }

  /**
   * Returns a png img
   *
   * @param array $stub or string $alias
   * @return Resource or null if not available
   */
  public function icon() {
    $icon = '';

    $endpoint = '';
    if ($this->oembed) {
      $endpoint = $this->format($this->config->get('endpoint', ''));
    }

    if (!$endpoint) {
      return $icon;
    }

    $pieces = parse_url($endpoint);
    $url = $pieces['host'];

    // Grab favicon from Google cache
    $icon = 'http://www.google.com/s2/favicons?domain=';
    $icon .= urlencode($url);

    return $icon;
  }

  public function thumbnail()
  {
    $thumbnail = '';
    if ($this->oembed) {
      $thumbnail = $this->oembed->get('thumbnail_url', '');
    }
    return $thumbnail;
  }

  public function type()
  {
    $type = $this->config->get('type', 'generic');
    if ($type === 'generic' && $this->embedCode && $this->oembed) {
      $type = $this->oembed->get('type', $type);
    }

    return $type;
  }

  public function author($key = 'name')
  {
    $author = '';
    if ($this->embedCode && $this->oembed) {
      $author = $this->oembed->get('author_' . strtolower($key), '');
    }
    return $author;
  }

  public function attributes($var = null, $reset = false)
  {
    if ($var !== null) {
      if ($reset) {
        $this->attributes = $var;
      } else {
        $this->attributes = array_replace_recursive($this->attributes, $var);
      }
    }
    if (!is_array($this->attributes)) {
      $this->attributes = [];
    }
    return $this->attributes;
  }

  public function params($var = null, $reset = false)
  {
    if ($var !== null) {
      if ($reset) {
        $this->params = $var;
      } else {
        $this->params = array_replace_recursive($this->params, $var);
      }
    }
    if (!is_array($this->params)) {
      $this->params = [];
    }
    return $this->params;
  }

  public function getEmbedCode($params = [])
  {
    $params = array_replace_recursive($this->params(), $params);
    $url = $this->url();

    $query = http_build_query($params);
    if (mb_strlen($query) > 0) {
      $query = (false === strpos($url, '?') ? '?' : '&') . $query;
    }
    return $url . $query;
  }

  /**
   * Returns information about the media. See http://www.oembed.com/.
   *
   * @return
   *   If oEmbed information is available, an array containing 'title', 'type',
   *   'url', and other information as specified by the oEmbed standard.
   *   Otherwise, NULL.
   */
  public function getOEmbed()
  {
    if ($this->oembed) {
      return $this->oembed;
    }

    $endpoint = $this->format($this->config->get('endpoint', ''));
    if (!$endpoint) {
      return [];
    }

    $response = \Requests::get($endpoint);
    if (!$response->success) {
      $response->throw_for_status();
    }

    return json_decode($response->body, true);
  }

  /**
   * Return the domain(s) of this media resource
   *
   * @return string
   */
  public function getDomains()
  {
    // Get domains of media resources
    $schemes = $this->base_config->get('schemes', []);

    // Ensure domains are of type array
    if (!is_array($schemes)) {
      $schemes = [$schemes];
    }

    $domains = [];
    foreach ($schemes as $scheme) {
      // Trick: extract domains from scheme attributes
      $domain = parse_url(str_replace('\.', '.', "http://$scheme"), PHP_URL_HOST);

      // Take out the www. in front of domain
      $domains[] = preg_replace("/^www\./", '', $domain);
    }

    // Faster alternative to PHP’s array unique function
    return array_keys(array_flip($domains));
  }

  public function onTwigTemplateVariables(Event $event)
  {
    $mediaembed = $event['mediaembed'];
    foreach ($this->config->get('assets', []) as $asset) {
      if (is_string($asset) && strlen($asset) > 0) {
        $mediaembed->add($asset);
      }
    }
  }

  /**
   * Convenience wrapper for `echo $ServiceProvider`
   *
   * @return string
   */
  public function __toString()
  {
    return $this->getEmbedCode();
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  protected function format($string, $params = [])
  {
    $keys = ['id', 'name', 'url'];
    foreach ($keys as $key) {
      if (!isset($params["{:$key}"])) {
        $params["{:$key}"] = $this->{$key}();
      }
    }
    $params += [
      '{:canonical}' => $this->config->get('canonical', ''),
    ];

    // Format URL placeholder with params
    $keys = ['{:url}', '{:canonical}'];
    foreach ($keys as $key) {
      $params[$key] = urlencode(str_ireplace(
        array_keys($params), $params, $params[$key])
      );
    }

    // Replace OEmbed calls with response
    $string = preg_replace_callback('~\{\:oembed(?:\.(?=\w))([\.\w_]+)?\}~i',
      function($match) {
        $ombed = $this->getOEmbed();
        return $oembed ? $oembed->get($match[1], '') : $match[0];
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
    } else {
      $parts['query'] = [];
    }

    return $parts;
  }
}
