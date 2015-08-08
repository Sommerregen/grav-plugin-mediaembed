<?php
/**
 * OEmbedInterface
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

/**
 * OEmbedInterface
 */
interface OEmbedInterface
{
	/**
	 * Initialize service.
	 */
	public function init($embedCode, $config = []);

	/**
	 * Reset service.
	 */
	public function reset();

	/**
	 * Extract and normalize id from embed code.
	 *
	 * @param  string $embedCode The embed code to be canonicalized
	 * @return string            Returns the canonicalized embed code,
	 *                           usually an id.
	 */
	public function canonicalize($embedCode);

	/**
	 * Returns the unique id of a media resource.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Returns the host as slugged string.
	 *
	 * @return string
	 */
	public function slug();

	/**
	 * Returns the name of this media.
	 *
	 * @return string
	 */
	public function name();

	/**
	 * Returns the title of this media.
	 *
	 * @return string
	 */
	public function title();

	/**
	 * Returns the description of this media.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * The URL of this media
	 *
	 * @return string
	 */
	public function url();

	/**
	 * The website where this media come from.
	 *
	 * @return string
	 */
	public function website();

	/**
   * Gets the thumbnail of the media and its dimensions.
   *
   * @return array
   */
  public function thumbnail();

	/**
	 * Returns the type of this media.
	 *
	 * @return string
	 */
	public function type();

	/**
	 * Gets the author and informations about him from the media.
	 *
	 * @return array
	 */
	public function author($key = 'name');

	/**
	 * Gets or sets object attributes about the media
	 *
	 * @param  bool $var Media attributes
	 *
	 * @return array     Returns object attributes about the media i.e.
	 *                   width, height and so on.
	 */
	public function attributes($var = [], $reset = false);

	/**
	 * Gets or sets object parameter about the media
	 *
	 * @param  bool $var Media parameter.
	 *
	 * @return array     Returns the object parameter about the media i.e.
	 *                   additional parameter for the request
	 */
	public function params($var = [], $reset = false);

	/**
	 * Returns the final HTML code for display.
	 *
	 * @return string
	 */
	public function getEmbedCode($params = []);

	/**
   * Returns information about the media. See http://www.oembed.com/.
   *
   * @return
   *   If oEmbed information is available, an array containing 'title', 'type',
   *   'url', and other information as specified by the oEmbed standard.
   *   Otherwise, NULL.
   */
	public function getOEmbed();

	/**
	 * Returns the accepted domains of this media resource
	 *
	 * @return array
	 */
	public function getDomains();

	/**
	 * Special Template events fired by Grav\Plugin\MediaEmbed\Service.
	 */
	// public function onTwigTemplatePaths();
	// public function onTwigTemplateVariables(Event $event);
}
