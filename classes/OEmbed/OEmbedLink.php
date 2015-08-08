<?php
/**
 * OEmbedLink
 *
 * This file is part of Grav MediaEmbed plugin.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 */

namespace Grav\Plugin\MediaEmbed\OEmbed;

use Grav\Plugin\MediaEmbed\OEmbed\OEmbed;

/**
 * OEmbedLink
 *
 * Responses of this type allow a provider to return any generic embed
 * data (such as title and author_name), without providing either the
 * url or html parameters. The consumer may then link to the resource,
 * using the URL specified in the original request.
 */
class OEmbedLink extends OEmbed
{
}
