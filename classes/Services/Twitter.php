<?php
/**
 * Twitter
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\Services;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbedRich;

/**
 * Twitter
 */
class Twitter extends OEmbedRich
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

    $response = \Requests::get($endpoint);
    if (!$response->success) {
      $response->throw_for_status();
    }

    $json = json_decode($response->body, true);

    $this->oembed = [
    	'type' => 'rich',
			'author_name' => $json['author_name'],
			'author_url' => 'https://twitter.com/' . $json['author_name'],
			'provider_name' => 'Twitter',
			'provider_url' => 'https://twitter.com/',
			'url' => 'https://www.twitter.com/' . $this->embedCode,
			'html' => $json['html'],
		];

    return $this->oembed;
  }
}