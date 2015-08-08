<?php
/**
 * OEmbedPhoto
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbed;

/**
 * OEmbedPhoto
 *
 * This type is used for representing static photos. The following
 * parameters are defined:
 *
 * url (required)
 *   The source URL of the image. Consumers should be able to insert
 *   this URL into an <img> element. Only HTTP and HTTPS URLs are valid.
 *
 * width (required)
 *   The width in pixels of the image specified in the url parameter.
 *
 * height (required)
 *   The height in pixels of the image specified in the url parameter.
 *
 * Responses of this type must obey the maxwidth and maxheight request
 * parameter.
 */
class OEmbedPhoto extends OEmbed
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
}
