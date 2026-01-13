<?php
/**
 * EGroupware AmirRAG - Hooks
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Amirrag;

use EGroupware\Api;

/**
 * AmirRAG Hooks
 */
class Hooks
{
	/**
	 * Hooks for admin and sidebox
	 */
	public static function allHooks($hook_data)
	{
		if (!$hook_data['location'])
		{
			$hook_data['location'] = $hook_data[0];
		}

		switch($hook_data['location'])
		{
			case 'sidebox_menu':
				self::sidebox_menu($hook_data['appname']);
				break;
			case 'admin':
				self::admin();
				break;
		}
	}

	/**
	 * Settings hook - these are SITE settings (admin config), not user preferences
	 */
	public static function settings()
	{
		$settings = array(
			'embedding_provider' => array(
				'type'   => 'select',
				'label'  => 'Embedding Provider',
				'name'   => 'embedding_provider',
				'values' => array(
					'openai' => 'OpenAI',
					'ionos'  => 'IONOS',
				),
				'default' => 'openai',
				'help'   => 'Which embedding service to use for generating vectors',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'embedding_api_key' => array(
				'type'   => 'password',
				'label'  => 'Embedding API Key',
				'name'   => 'embedding_api_key',
				'help'   => 'API key for the embedding service',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'embedding_api_url' => array(
				'type'   => 'input',
				'label'  => 'Embedding API URL',
				'name'   => 'embedding_api_url',
				'help'   => 'API URL for IONOS embedding service (leave empty for OpenAI)',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'embedding_model' => array(
				'type'   => 'input',
				'label'  => 'Embedding Model',
				'name'   => 'embedding_model',
				'default' => 'text-embedding-ada-002',
				'help'   => 'Model name for embeddings (e.g., text-embedding-ada-002 for OpenAI, BAAI/bge-m3 for IONOS)',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_provider' => array(
				'type'   => 'select',
				'label'  => 'LLM Provider',
				'name'   => 'llm_provider',
				'values' => array(
					'openai' => 'OpenAI',
					'ionos'  => 'IONOS',
				),
				'default' => 'openai',
				'help'   => 'Which LLM service to use for generating responses',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_api_key' => array(
				'type'   => 'password',
				'label'  => 'LLM API Key',
				'name'   => 'llm_api_key',
				'help'   => 'API key for the LLM service',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_api_url' => array(
				'type'   => 'input',
				'label'  => 'LLM API URL',
				'name'   => 'llm_api_url',
				'help'   => 'API URL for IONOS LLM service (leave empty for OpenAI)',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_model' => array(
				'type'   => 'input',
				'label'  => 'LLM Model',
				'name'   => 'llm_model',
				'default' => 'gpt-3.5-turbo',
				'help'   => 'Model name for LLM (e.g., gpt-3.5-turbo for OpenAI, meta-llama/Llama-3.3-70B-Instruct for IONOS)',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_temperature' => array(
				'type'   => 'input',
				'label'  => 'LLM Temperature',
				'name'   => 'llm_temperature',
				'default' => '0.3',
				'help'   => 'Temperature for LLM responses (0.0-1.0, lower is more deterministic)',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'llm_max_tokens' => array(
				'type'   => 'input',
				'label'  => 'LLM Max Tokens',
				'name'   => 'llm_max_tokens',
				'default' => '600',
				'help'   => 'Maximum tokens for LLM responses',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'chunk_size' => array(
				'type'   => 'input',
				'label'  => 'Text Chunk Size',
				'name'   => 'chunk_size',
				'default' => '1000',
				'help'   => 'Size of text chunks for indexing',
				'admin'  => true,
				'xmlrpc' => true,
			),
			'chunk_overlap' => array(
				'type'   => 'input',
				'label'  => 'Text Chunk Overlap',
				'name'   => 'chunk_overlap',
				'default' => '200',
				'help'   => 'Overlap between text chunks',
				'admin'  => true,
				'xmlrpc' => true,
			),
		);
		return $settings;
	}

	/**
	 * Sidebox menu
	 */
	private static function sidebox_menu($appname)
	{
		$file = array(
			'Search' => Api\Egw::link('/index.php', array(
				'menuaction' => 'amirrag.'.Ui::class.'.index',
				'ajax' => 'true'
			)),
			'Index Data' => Api\Egw::link('/index.php', array(
				'menuaction' => 'amirrag.'.Ui::class.'.indexData',
				'ajax' => 'true'
			)),
		);
		
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file['Configuration'] = Api\Egw::link('/index.php', array(
				'menuaction' => 'admin.admin_config.index',
				'appname' => 'amirrag',
				'ajax' => 'true'
			));
		}
		
		display_sidebox($appname, lang('Menu'), $file);
	}

	/**
	 * Admin hook
	 */
	private static function admin()
	{
		$file = Array(
			'Site Configuration' => Api\Egw::link('/index.php','menuaction=admin.admin_config.index&appname=amirrag&ajax=true'),
		);
		
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			display_section('amirrag', $file);
		}
	}

	/**
	 * Hook for data changes in supported apps
	 * Queues items for indexing
	 */
	public static function dataChange($hook_data)
	{
		$location = $hook_data['location'];
		$app_name = '';
		
		// Determine app name from hook location
		if (strpos($location, 'addressbook') !== false)
		{
			$app_name = 'addressbook';
		}
		elseif (strpos($location, 'calendar') !== false)
		{
			$app_name = 'calendar';
		}
		elseif (strpos($location, 'infolog') !== false)
		{
			$app_name = 'infolog';
		}
		
		if (!$app_name || empty($hook_data['id']))
		{
			return;
		}
		
		// Queue the item for indexing
		$bo = new Bo();
		$bo->queueForIndexing($app_name, $hook_data['id']);
	}

	/**
	 * Hook called by link-class to include amirrag in the appregistry of the linkage
	 *
	 * @param array|string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	public static function search_link($location)
	{
		unset($location);	// not used, but required by function signature

		return array(
			'list' => array(
				'menuaction' => 'amirrag.EGroupware\\Amirrag\\Ui.index',
				'ajax' => 'true'
			),
			'entry' => 'Search',
			'entries' => 'Searches',
		);
	}
}
