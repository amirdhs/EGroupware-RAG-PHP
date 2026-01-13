<?php
/**
 * EGroupware AmirRAG - Database Updates
 *
 * @package amirrag
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

function amirrag_upgrade23_1()
{
	return $GLOBALS['setup_info']['amirrag']['currentver'] = '23.1.001';
}
