<?php
/**
 * MediaEmbed v1.2.0
 *
 * This plugin embeds several media sites (e.g. YouTube, Vimeo,
 * Soundcloud) by only providing the URL to the medium.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 *
 * @package     MediaEmbed
 * @version     1.2.0
 * @link        <https://github.com/sommerregen/grav-plugin-archive-plus>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>        MIT
 * @license     <http://opensource.org/licenses/GPL-3.0>    GPLv3
 */

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

use Grav\Plugin\MediaEmbed\Autoloader;
use Grav\Plugin\MediaEmbed\MediaEmbed;

/**
 * MediaEmbed
 *
 * This plugin ...
 */
class MediaEmbedPlugin extends Plugin
{
  /**
   * @var MediaEmbedPlugin
   */

  /** ---------------------------
   * Private/protected properties
   * ----------------------------
   */

  /**
   * Instance of MediaEmbed class
   *
   * @var object
   */
  protected $mediaembed;

  /** -------------
   * Public methods
   * --------------
   */

  /**
   * Return a list of subscribed events.
   *
   * @return array    The list of events of the plugin of the form
   *                      'name' => ['method_name', priority].
   */
  public static function getSubscribedEvents()
  {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
    ];
  }

  /**
   * Initialize configuration.
   */
  public function onPluginsInitialized()
  {
    if ($this->isAdmin()) {
      $this->active = false;
      return;
    }

    if ($this->config->get('plugins.mediaembed.enabled')) {
      // Initialize Autoloader
      require_once(__DIR__ . '/classes/Autoloader.php');
      require_once(__DIR__ . '/vendor/Requests/library/Requests.php');

      $autoloader = new Autoloader();
      $autoloader->route([
        'Requests_' => __DIR__ . '/vendor/Requests/library/Requests',
      ], false);
      $autoloader->register();

      // Initialize MediaEmbed class
      $this->mediaembed = new MediaEmbed($this->config);

      $this->enable([
        'onPageContentRaw' => ['onPageContentRaw', 0],
        'onPageContentProcessed' => ['onPageContentProcessed', 0],
        'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
      ]);
    }
  }

  /**
   * Add content after page content was read into the system.
   *
   * @param  Event  $event An event object, when `onPageContentRaw` is
   *                       fired.
   */
  public function onPageContentRaw(Event $event)
  {
    /** @var Page $page */
    $page = $event['page'];
    $config = $this->mergeConfig($page);

    if ($config->get('enabled')) {
      // Get raw content and substitute all formulas by a unique token
      $raw_content = $page->getRawContent();

      // Save modified page content with tokens as placeholders
      $page->setRawContent(
        $this->mediaembed->prepare($raw_content, $page->id())
      );
    }
  }

  /**
   * Apply mediaembed filter to content, when each page has not been
   * cached yet.
   *
   * @param  Event  $event The event when 'onPageContentProcessed' was
   *                       fired.
   */
  public function onPageContentProcessed(Event $event)
  {
    /** @var Page $page */
    $page = $event['page'];

    $config = $this->mergeConfig($page);
    if ($config->get('enabled') && $this->compileOnce($page)) {
      // Get content
      $content = $page->getRawContent();

      // Apply MediaEmbed filter and save modified page content
      $page->setRawContent(
        $this->mediaembed->process($content, $config)
      );
    }
  }

  /**
   * Add current directory to twig lookup paths.
   */
  public function onTwigTemplatePaths()
  {
    // Register MediaEmbed Twig templates
    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';

    // Fire event for MediaEmbed plugins
    $this->mediaembed->fireEvent('onTwigTemplatePaths');
  }

  /**
   * Set needed variables to display videos.
   */
  public function onTwigSiteVariables()
  {
    // Register built-in CSS assets
    if ($this->config->get('plugins.mediaembed.built_in_css')) {
      $this->grav['assets']
        ->add('plugin://mediaembed/assets/css/mediaembed.css');
    }

    if ($this->config->get('plugins.mediaembed.built_in_js')) {
      $this->grav['assets']
        ->add('plugin://mediaembed/assets/js/mediaembed.js');
    }

    // Register assets from MediaEmbed Services
    $assets = $this->mediaembed->getAssets();
    foreach ($assets as $asset) {
      $this->grav['assets']->add($asset);
    }
  }

  /** -------------------------------
   * Private/protected helper methods
   * --------------------------------
   */

  /**
   * Checks if a page has already been compiled yet.
   *
   * @param  Page    $page The page to check
   *
   * @return boolean       Returns TRUE if page has already been
   *                       compiled yet, FALSE otherwise
   */
  protected function compileOnce(Page $page)
  {
    static $processed = [];

    $id = md5($page->path());
    // Make sure that contents is only processed once
    if (!isset($processed[$id]) || ($processed[$id] < $page->modified())) {
      $processed[$id] = $page->modified();
      return true;
    }

    return false;
  }
}
