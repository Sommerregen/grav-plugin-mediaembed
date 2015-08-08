<?php
/**
 * Slides.com
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\Services;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbedRich;

/**
 * Slides
 */
class Slides extends OEmbedRich
{
	public function getOEmbed()
  {
    if ($this->oembed) {
      return $this->oembed;
    }

    $endpoint = $this->format($this->config->get('endpoint', ''));
    if (!$endpoint) {
      return [];
    }

    // Extract owner from embed code
    list($owner, $id) = explode('/', $this->embedCode, 2);

    // Fake response
    $this->oembed = [
    	'type' => 'rich',
    	'title' => '',
    	'description' => '',
			'author_name' => $owner,
			'author_url' => 'http://slides.com/'.$owner,
			'provider' => 'Slides',
			'provider_url' => 'http://slides.com',
			'url' => 'http://slides.com/'.$this->embedCode,
      'html' => '<iframe src="//slides.com/'.rtrim($this->embedCode, '/').'/embed" width="576" height="420" scrolling="no" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
      'width' => 576,
      'height' => 420,
		];

    return $this->oembed;
  }

  public function getEmbedCode($params = [])
  {
    $embed = parent::getEmbedCode($params);
    if ($this->embedCode && $this->oembed) {
      // Inject parameters directly into HTML OEmbed attribute
      $query = http_build_query($this->params());
      $url = $this->attributes['protocol'].'slides.com/'.rtrim($this->embedCode, '/').'/embed';
      if (mb_strlen($query) > 0) {
        $url .= (false === strpos($url, '?') ? '?' : '&') . $query;
      }

      // Get width and height
      $width = $this->attributes['width'];
      $height = $this->attributes['height'];

      $embed = '<iframe src="'.$url.'" width="'.$width.'" height="'.$height.'" scrolling="no" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
    }

    return $embed;
  }
}
