<?php
/**
 * EGroupware AmirRAG - Main entry point
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'amirrag',
		'noheader' => false,
		'nonavbar' => false,
	)
);

include('../header.inc.php');

// Redirect to the main interface
Api\Framework::redirect_link('/index.php', array(
	'menuaction' => 'amirrag.EGroupware\\Amirrag\\Ui.index',
	'ajax' => 'true',
));
