<?php
/**
 * OEmbedRich
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbed;

/**
 * OEmbedRich
 *
 * This type is used for rich HTML content that does not fall under one
 * of the other categories. The following parameters are defined:
 *
 * 	html (required)
 * 	 	The HTML required to display the resource. The HTML should have no
 * 	 	padding or margins. Consumers may wish to load the HTML in an
 * 	  off-domain iframe to avoid XSS vulnerabilities. The markup should
 * 	 	be valid XHTML 1.0 Basic.
 *
 * 	width (required)
 * 		The width in pixels required to display the HTML.
 *
 * 	height (required)
 * 		The height in pixels required to display the HTML.
 *
 * Responses of this type must obey the maxwidth and maxheight request
 * parameters.
 */
class OEmbedRich extends OEmbed
{
	public function getOEmbed()
  {
  	$oembed = parent::getOEmbed();

    $sizes = ['width', 'height'];
    foreach ($sizes as $key) {
      $size = isset($oembed[$key]) ? $oembed[$key] : 0;
      if (!preg_match('~^\d+$~', $size)) {
        $oembed[$key] = 0;
      }
    }

  	return $oembed;
  }

  public function getEmbedCode($params = [])
  {
  	$embed = parent::getEmbedCode($params);
  	if ($this->embedCode && $this->oembed) {
      $embed = $this->oembed->get('html', '');
  	}

  	return $embed;
  }
}
