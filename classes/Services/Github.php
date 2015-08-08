<?php
/**
 * GitHub
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\Services;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbedRich;

/**
 * GitHub
 */
class GitHub extends OEmbedRich
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
    	'title' => reset($json['files']),
    	'description' => $json['description'],
			'author_name' => $json['owner'],
			'author_url' => 'http://github.com/' . $json['owner'],
			'provider' => 'GitHub',
			'provider_url' => 'http://gist.github.com/',
			'url' => 'http://www.gist.github.com/' . $this->embedCode,
			'html' => $json['div'],
		];

		$this->config->join('assets', [$json['stylesheet']]);
    return $this->oembed;
  }
}
