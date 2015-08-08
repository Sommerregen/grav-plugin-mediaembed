<?php
/**
 * OEmbedVideo
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbed;

/**
 * OEmbedVideo
 *
 * This type is used for representing playable videos. The following
 * parameters are defined:
 *
 * html (required)
 *   The HTML required to embed a video player. The HTML should have
 *   no padding or margins. Consumers may wish to load the HTML in an
 *   off-domain iframe to avoid XSS vulnerabilities.
 *
 *  width (required)
 *    The width in pixels required to display the HTML.
 *
 *  height (required)
 *    The height in pixels required to display the HTML.
 *
 * Responses of this type must obey the maxwidth and maxheight request
 * parameters. If a provider wishes the consumer to just provide a
 * thumbnail, rather than an embeddable player, they should instead
 * return a photo response type.
 */
class OEmbedVideo extends OEmbed
{
	public function getOEmbed()
  {
  	$oembed = parent::getOEmbed();

  	if ($this->embedCode && $this->oembed) {
  		$width = $this->oembed->get('width');
  		$height = $this->oembed->get('height');
  		$this->attributes(['width' => $width, 'height' => $height]);
  	}

  	return $oembed;
  }

  public function getEmbedCode($params = [])
  {
  	$embed = parent::getEmbedCode($params);
  	if (mb_strlen($embed) == 0 && $this->embedCode && $this->oembed) {
  		$embed = $this->oembed->get('html', '');
  	}

  	return $embed;
  }
}
