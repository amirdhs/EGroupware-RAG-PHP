<?php
/**
 * EGroupware AmirRAG - Retrieval-Augmented Generation System
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Amirrag\Hooks;
use EGroupware\Amirrag\Ui;

$setup_info['amirrag']['name']      = 'amirrag';
$setup_info['amirrag']['version']   = '23.1.001';
$setup_info['amirrag']['app_order'] = 5;
$setup_info['amirrag']['tables']    = ['egw_amirrag_documents', 'egw_amirrag_index_queue'];
$setup_info['amirrag']['enable']    = 1;
$setup_info['amirrag']['index']     = 'amirrag.'.Ui::class.'.index&ajax=true';

$setup_info['amirrag']['author'] =
$setup_info['amirrag']['maintainer'] = array(
	'name'  => 'Amir',
	'email' => 'amir@egroupware.org',
);
$setup_info['amirrag']['license']  = 'GPL';
$setup_info['amirrag']['description'] = 'A complete Retrieval-Augmented Generation (RAG) system for EGroupware with semantic search and natural language question answering across Addressbook, Calendar, and InfoLog.';
$setup_info['amirrag']['note'] = 'Requires MariaDB 10.2+ for vector operations. Configure OpenAI or IONOS API keys in admin settings.';

/* The hooks this app includes, needed for hooks registration */
$setup_info['amirrag']['hooks']['admin'] = Hooks::class.'::allHooks';
$setup_info['amirrag']['hooks']['sidebox_menu'] = Hooks::class.'::allHooks';
$setup_info['amirrag']['hooks']['settings'] = Hooks::class.'::settings';
$setup_info['amirrag']['hooks']['search_link'] = Hooks::class.'::search_link';
// Hook into data changes for automatic indexing
$setup_info['amirrag']['hooks']['addressbook_edit'] = Hooks::class.'::dataChange';
$setup_info['amirrag']['hooks']['calendar_edit'] = Hooks::class.'::dataChange';
$setup_info['amirrag']['hooks']['infolog_edit'] = Hooks::class.'::dataChange';

/* Dependencies for this app to work */
$setup_info['amirrag']['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('23.1')
);
