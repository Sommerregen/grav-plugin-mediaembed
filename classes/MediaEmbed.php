<?php
/**
 * MediaEmbed
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed;

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Plugin\MediaEmbed\Service;
use RocketTheme\Toolbox\Event\Event;

/**
 * MediaEmbed
 *
 * Helper class to embed several media sites (e.g. YouTube, Vimeo,
 * Soundcloud) by only providing the URL to the medium.
 */
class MediaEmbed
{
  /**
   * @var MediaEmbed
   */
	use GravTrait;

  /** ---------------------------
   * Private/protected properties
   * ----------------------------
   */

  /**
   * A unique identifier
   *
   * @var string
   */
  protected $id;

  /**
   * A key-valued array used for hashing math formulas of a page
   *
   * @var array
   */
  protected $hashes;

  /**
   * @var array
   */
  protected $config;

  /**
   * @var array
   */
  protected $assets = [];

  /**
   * @var Grav\Plugin\MediaEmbed\Service
   */
  protected $service;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Constructor
   *
   * @param [type] $config [description]
   */
  public function __construct($config)
  {
    // Initialize Service class
    $this->service = new Service();
    $this->config = $config;
    $this->hashes = [];

    $services = $this->config->get('plugins.mediaembed.services', []);
    foreach ($services as $name => $config) {
      if (!$config['enabled']) {
        continue;
      }

      // Load providers in directory "services"
      $class =  __NAMESPACE__ . "\\Services\\$name";
      if (!class_exists($class)) {
        // Fallback to a more generic one
        $type = isset($config['type']) ? $config['type'] : '';
        $class = __NAMESPACE__."\\OEmbed\\OEmbed".ucfirst($type);
      }

      // Populate config
      $config['media'] = $this->config->get('plugins.mediaembed.media', []);
      $config['name'] = $name;

      if (class_exists($class)) {
        // Load ServiceProvider
        $provider = new $class($config);

        // Register ServiceProvider
        $this->service->register($provider);
      }
    }
  }

  /**
   * Gets and sets the identifier for hashing.
   *
   * @param  string $var the identifier
   *
   * @return string      the identifier
   */
  public function id($var = null)
  {
    if ($var !== null) {
      $this->id = $var;
    }
    return $this->id;
  }

  public function prepare($content, $id = '')
  {
    // Set unique identifier based on page content
    $this->id(md5(time() . $id . md5($content)));

    // Reset class hashes before processing
    $this->reset();

    $regex = "~
    (               # wrap whole match in $1
      !\\[
        (?P<alt>.*?)       # alt text = $2
      \\]
      \\(           # literal paren
        [ \\t]*
        <?(?P<src>\S+?)>?  # src url = $3
        [ \\t]*
        (           # $4
          (['\"])   # quote char = $5
          (?<title>.*?)     # title = $6
          \\5       # matching quote
          [ \\t]*
        )?          # title is optional
      \\)
    )
    ~xs";

    // Replace all mediaembed links by a (unique) hash
    $content = preg_replace_callback($regex, function($matches) {
      // Get the url and parse it
      $url = parse_url(htmlspecialchars_decode($matches[3]));

      // If there is no host set but there is a path, the file is local
      if (!isset($url['host']) && isset($url['path'])) {
        return $matches[0];
      }

      if (!isset($matches['title'])) {
        $matches['title'] = '';
      }

      return $this->hash($matches[0], $matches);
    }, $content);

    return $content;
  }

	public function process($content, $config = [])
  {
    /** @var Twig $twig */
    $twig = self::getGrav()['twig'];

    // Initialize unique per-page counter
    $uid = 1;

    // '~(<p>)?\s*<a[^>]*href\s*=\s*([\'"])(?P<href>.*?)\2[^>]*>(?P<code>.*?)</a>\s*(?(1)(</p>))~i',

    // Get all <a> tags and extract "href" attribute
    $content = preg_replace_callback(
      '~mediaembed::([0-9a-z]+)::([0-9]+)::M~i',
      function($match) use ($twig, &$uid, $config) {
        list($embed, $data) = $this->hashes[$match[0]];

        // Check if a service for a specific domain is registered
        if ($this->service->match($data['src'])) {
          $mediaembed = [
            'uid' => $uid++,
            'service' => null,
            'config' => $config,

            'raw' => [
              'alt' => $data['alt'],
              'title' => $data['title'],
              'src' => html_entity_decode($data['src']),
            ],

            'success' => true,
            'message' => '',
          ];

          // Load and get data of OEmbed Media Service
          try {
            $provider = $this->service->embed($data['src']);
          } catch (\Exception $e) {
            $mediaembed['message'] = $e->getMessage();
            $mediaembed['success'] = false;
          }

          // Setup variables for embedding OEmbed Media Service
          if ($mediaembed['success']) {
            // Get assets/options of current provider
            $assets = $provider->onTwigTemplateVariables(
              new Event(['service' => $this->service, 'mediaembed' => $this])
            );

            // Assets are passed by value as an array
            if (is_array($assets)) {
              $this->addAssets($assets);
            }

            // Add OEmbed Service to variables
            $mediaembed['service'] = $provider;

            // TODO: Cache contents from thumbnail and url
          }

          // Embed OEmbed Media
          $vars = ['mediaembed' => $mediaembed];
          $template = 'partials/mediaembed' . TEMPLATE_EXT;
          $embed = $twig->processTemplate($template, $vars);
        } else {
          $text = (strlen($data['alt']) > 0) ? $data['alt'] : $data['src'];
          $attributes = [
            'href' => $data['src'],
            'title' => $data['title'],
          ];
          foreach ($attributes as $key => $value) {
            if (strlen($value) == 0) {
              unset($attributes[$key]);
            } else {
              $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
              $attributes[$key] = $key . '="' . $value . '"';
            }
          }

          $attributes = $attributes ? ' ' . implode(' ', $attributes) : '';

          // Transform embed media to link for compatibility
          $embed = sprintf('<a%s>%s</a>', $attributes, $text);
        }

        return $embed;
    }, $content);

    $this->reset();
    // Write content back to page
    return $content;
  }

  /**
   * Fires an event with optional parameters.
   *
   * @param  string $eventName The name of the event.
   * @param  Event  $event     Optional parameter to be passed to the
   *                           called methods.
   * @return Event
   */
  public function fireEvent($eventName, Event $event = null)
  {
    // Dispatch event; just propagate it to service class
    return $this->service->call($eventName, $event);
  }

  /**
   * Get assets of loaded media services.
   *
   * @param boolean $reset Toggle whether to reset assets after retrieving
   *                       or not.
   */
  public function getAssets($reset = true)
  {
    $assets = $this->assets;
    if ($reset) {
      $this->assets = [];
    }

    return $assets;
  }

  /**
   * Add assets to the queue of MediaEmbed plugin
   *
   * @param array   $assets An array of assets to add.
   * @param boolean $append Append assets to array or reset assets.
   */
  public function addAssets($assets, $append = true)
  {
    // Append or reset assets
    if (!$append) {
      $this->assets = [];
    }

    // Wrap non-array assets in an array
    if (!is_array($assets)) {
      $assets = array($assets);
    }

    // Merge assets
    $assets = array_merge($this->assets, $assets);

    // Remove duplicates
    $this->assets = array_keys(array_flip($assets));
  }

  /**
   * Add assets to the queue of MediaEmbed plugin
   *
   * Alias for `addAssets`
   *
   * @param array   $assets An array of assets to add.
   * @param boolean $append Append assets to array or reset assets.
   */
  public function add($assets, $append = true)
  {
    return $this->addAssets($assets, $append);
  }

  /**
   * Add assets to the queue of MediaEmbed plugin
   *
   * Alias for `addAssets`
   *
   * @param array   $assets An array of assets to add.
   * @param boolean $append Append assets to array or reset assets.
   */
  public function addCss($assets, $append = true)
  {
    return $this->addAssets($assets, $append);
  }

  /**
   * Add assets to the queue of MediaEmbed plugin
   *
   * Alias for `addAssets`
   *
   * @param array   $assets An array of assets to add.
   * @param boolean $append Append assets to array or reset assets.
   */
  public function addJs($assets, $append = true)
  {
    return $this->addAssets($assets, $append);
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  /**
   * Get cached media or media with key.
   *
   * @param  string $key The key to load from the cache.
   * @return mixed       The media content.
   */
  protected function getCachedMedia($key)
  {
    /** @var Cache $cache */
    $cache = $grav['cache'];

    // Check, if cache should be used or not
    if ($this->config->get('cache.enabled')) {
      // Get cache id and try to fetch data
      $cache_id = md5('mediaembed' . $key . $cache->getKey());
      $data = $cache->fetch($cache_id);

      if ((false === $data) || (time() > $data['expire'])) {
        // Pack and provide data with a time stamp.
        $data = array(
          'content' => $this->service->embed($key),
          'expire' => time() + $this->config->get('cache.lifetime'),
        );
        $cache->save($cache_id, $data);
      }

      // Return data contents
      $content = $data['content'];
    } else {
      // Just call callback and return result
      $content = $this->service->embed($key);
    }

    return $content;
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

  /**
   * Reset MathJax class
   */
  protected function reset()
  {
    $this->hashes = [];
  }

  /**
   * Hash a given text.
   *
   * Called whenever a tag must be hashed when a function insert an
   * atomic element in the text stream. Passing $text to through this
   * function gives a unique text-token which will be reverted back when
   * calling unhash.
   *
   * @param  string $text The text to be hashed
   * @param  string $type The type (category) the text should be saved
   *
   * @return string       Return a unique text-token which will be
   *                      reverted back when calling unhash.
   */
  protected function hash($text, $data = [])
  {
    static $counter = 0;

    // Swap back any tag hash found in $text so we do not have to `unhash`
    // multiple times at the end.
    $text = $this->unhash($text);

    // Then hash the block
    $key = implode('::', array('mediaembed', $this->id, ++$counter, 'M'));
    $this->hashes[$key] = [$text, $data];

    // String that will replace the tag
    return $key;
  }

  /**
   * Swap back in all the tags hashed by hash.
   *
   * @param  string $text The text to be un-hashed
   *
   * @return string       A text containing no hash inside
   */
  protected function unhash($text)
  {
    $text = preg_replace_callback(
      '~mediaembed::([0-9a-z]+)::([0-9]+)::M~i', function($atches) {
      return $this->hashes[$matches[0]][0];
    }, $text);

    return $text;
  }
}
