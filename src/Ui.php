<?php
/**
 * EGroupware AmirRAG - User Interface
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Amirrag;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * AmirRAG User Interface
 */
class Ui
{
	/**
	 * @var Bo Business object
	 */
	private $bo;
	
	/**
	 * @var string|null Initialization error message
	 */
	private $init_error;

	/**
	 * Public functions
	 * @var array
	 */
	public $public_functions = [
		'index' => true,
		'indexData' => true,
		'testConnection' => true,
	];

	/**
	 * Constructor
	 */
	public function __construct()
	{
		try
		{
			$this->bo = new Bo();
			$this->init_error = null;
		}
		catch (\Exception $e)
		{
			// Log error but allow UI to load
			error_log('AmirRAG Ui initialization error: ' . $e->getMessage());
			$this->init_error = $e->getMessage();
		}
	}

	/**
	 * Main search interface
	 * 
	 * @param array $content = null
	 */
	public function index($content=null)
	{
		// Debug logging
		error_log('AmirRAG Ui::index() called');
		
		// Show initialization error if any
		if ($this->init_error)
		{
			Api\Framework::message('AmirRAG initialization error: ' . $this->init_error, 'error');
		}
		
		if (is_array($content))
		{
			// Handle button press
			if (!empty($content['button']))
			{
				$button = key($content['button']);
				unset($content['button']);
				
				switch($button)
				{
					case 'indexdata':
						// Redirect to indexData page
						Api\Framework::redirect_link('/index.php', array(
							'menuaction' => 'amirrag.'.self::class.'.indexData',
							'ajax' => 'true'
						));
						return;
					case 'config':
						// Redirect to configuration page
						Api\Framework::redirect_link('/index.php', array(
							'menuaction' => 'admin.admin_config.index',
							'appname' => 'amirrag',
							'ajax' => 'true'
						));
						return;
				}
			}
			
			// Handle search
			if (!empty($content['search_query']) && $this->bo)
			{
				try
				{
					$app_filter = $content['app_filter'] ?? '';
					$use_llm = !empty($content['use_llm']);
					
					$search_result = $this->bo->search($content['search_query'], $app_filter, $use_llm);
					
					$content['results'] = $search_result['results'] ?? [];
					$content['llm_response'] = $search_result['llm_response'] ?? '';
					
					// Transform results for display
					$content['results_grid'] = $this->formatResults($content['results']);
					
					if (empty($content['results']))
					{
						Api\Framework::message('No results found. Make sure you have indexed data first (go to Index Data).', 'info');
					}
				}
				catch (\Exception $e)
				{
					Api\Framework::message('Search error: ' . $e->getMessage(), 'error');
				}
			}
		}
		else
		{
			// Initial load
			$content = [
				'search_query' => '',
				'app_filter' => '',
				'use_llm' => true,
				'results_grid' => [],
				'llm_response' => '',
			];
			
			// Load statistics
			if ($this->bo)
			{
				try
				{
					$stats = $this->bo->getStatistics();
					$content['stats_text'] = $this->formatStats($stats);
					
					// Warn if no data indexed
					if ($stats['total'] == 0)
					{
						Api\Framework::message('No data indexed yet! Please go to "Index Data" first to embed your Addressbook, Calendar, and InfoLog data.', 'warning');
					}
				}
				catch (\Exception $e)
				{
					$content['stats_text'] = 'Statistics unavailable: ' . $e->getMessage();
				}
			}
			else
			{
				$content['stats_text'] = 'AmirRAG not initialized. Please check configuration in Admin → Site Configuration → amirrag.';
			}
		}

		$sel_options = [
			'app_filter' => [
				'' => 'All Applications',
				'addressbook' => 'Addressbook',
				'calendar' => 'Calendar',
				'infolog' => 'InfoLog',
			],
		];

		$tpl = new Etemplate('amirrag.index');
		$tpl->exec('amirrag.EGroupware\\Amirrag\\Ui.index', $content, $sel_options, null, null, 0);
	}

	/**
	 * Data indexing interface
	 * 
	 * @param array $content = null
	 */
	public function indexData($content=null)
	{
		// Check configuration first
		$config = Api\Config::read('amirrag');
		$config_status = $this->checkConfiguration($config);
		
		if (!$this->bo)
		{
			Api\Framework::message('AmirRAG not initialized: ' . $this->init_error, 'error');
			$content = [
				'index_app' => 'all',
				'index_limit' => 100,
				'index_results' => 'Error: AmirRAG not initialized. Please configure API settings first.',
				'stats_text' => 'Not available',
				'config_status' => $config_status,
			];
			
			$sel_options = [
				'index_app' => [
					'all' => 'All Applications',
					'addressbook' => 'Addressbook',
					'calendar' => 'Calendar',
					'infolog' => 'InfoLog',
				],
			];
			
			$tpl = new Etemplate('amirrag.indexdata');
			$tpl->exec('amirrag.EGroupware\\Amirrag\\Ui.indexData', $content, $sel_options, null, null, 2);
			return;
		}
		
		if (is_array($content))
		{
			// Handle test connection
			if (!empty($content['test_connection']))
			{
				$test_result = $this->testEmbeddingConnection();
				$content['index_results'] = $test_result;
				
				if (strpos($test_result, '✓') !== false)
				{
					Api\Framework::message('Connection test successful!', 'success');
				}
				else
				{
					Api\Framework::message('Connection test failed!', 'error');
				}
			}
			// Handle indexing request
			elseif (!empty($content['index_action']))
			{
				$app = $content['index_app'] ?? '';
				$limit = intval($content['index_limit'] ?? 0);
				
				if ($app === 'all')
				{
					// Index all apps
					$results = [];
					foreach (['addressbook', 'calendar', 'infolog'] as $app_name)
					{
						try
						{
							$result = $this->bo->indexApp($app_name, $limit);
							$results[] = $result;
						}
						catch (\Exception $e)
						{
							$results[] = [
								'success' => false,
								'app_name' => $app_name,
								'error' => $e->getMessage(),
							];
						}
					}
					
					$content['index_results'] = $this->formatIndexResults($results);
					Api\Framework::message('Indexing completed', 'success');
				}
				elseif (!empty($app))
				{
					// Index single app
					try
					{
						$result = $this->bo->indexApp($app, $limit);
						$content['index_results'] = $this->formatIndexResults([$result]);
						
						if ($result['success'])
						{
							Api\Framework::message("Indexed {$result['indexed']} items from {$app}", 'success');
						}
						else
						{
							Api\Framework::message("Indexing failed: " . $result['error'], 'error');
						}
					}
					catch (\Exception $e)
					{
						Api\Framework::message('Indexing error: ' . $e->getMessage(), 'error');
						$content['index_results'] = "✗ Error: " . $e->getMessage();
					}
				}
			}
			elseif (!empty($content['clear_index']))
			{
				// Clear index
				try
				{
					$this->bo->clearIndex();
					Api\Framework::message('Index cleared successfully', 'success');
					$content['index_results'] = "✓ All indexed data has been cleared.";
				}
				catch (\Exception $e)
				{
					Api\Framework::message('Error clearing index: ' . $e->getMessage(), 'error');
				}
			}
			
			// Refresh stats
			try
			{
				$stats = $this->bo->getStatistics();
				$content['stats_text'] = $this->formatStats($stats);
			}
			catch (\Exception $e)
			{
				$content['stats_text'] = 'Statistics unavailable';
			}
		}
		else
		{
			// Initial load
			$content = [
				'index_app' => 'all',
				'index_limit' => 100,
				'index_results' => '',
				'config_status' => $config_status,
			];
			
			// Load statistics
			try
			{
				$stats = $this->bo->getStatistics();
				$content['stats_text'] = $this->formatStats($stats);
			}
			catch (\Exception $e)
			{
				$content['stats_text'] = 'Statistics unavailable';
			}
		}

		$sel_options = [
			'index_app' => [
				'all' => 'All Applications',
				'addressbook' => 'Addressbook',
				'calendar' => 'Calendar',
				'infolog' => 'InfoLog',
			],
		];

		$tpl = new Etemplate('amirrag.indexdata');
		$tpl->exec('amirrag.EGroupware\\Amirrag\\Ui.indexData', $content, $sel_options, null, null, 0);
	}

	/**
	 * Format search results for display
	 *
	 * @param array $results
	 * @return array
	 */
	private function formatResults($results)
	{
		$formatted = [];
		
		foreach ($results as $result)
		{
			$formatted[] = [
				'app' => $result['app_name'],
				'content' => substr($result['content'], 0, 200) . '...',
				'similarity' => number_format($result['similarity'] * 100, 2) . '%',
				'doc_id' => $result['doc_id'],
			];
		}
		
		return $formatted;
	}

	/**
	 * Format indexing results
	 *
	 * @param array $results
	 * @return string
	 */
	private function formatIndexResults($results)
	{
		$text = "=== Indexing Results ===\n\n";
		
		foreach ($results as $result)
		{
			$app = $result['app_name'];
			
			if ($result['success'])
			{
				$text .= "✓ {$app}: Indexed {$result['indexed']} items\n";
				
				if (!empty($result['errors']))
				{
					$text .= "  Warnings: " . count($result['errors']) . "\n";
					// Show first few errors
					foreach (array_slice($result['errors'], 0, 3) as $err)
					{
						$text .= "    - {$err}\n";
					}
					if (count($result['errors']) > 3)
					{
						$text .= "    ... and " . (count($result['errors']) - 3) . " more\n";
					}
				}
			}
			else
			{
				$text .= "✗ {$app}: Failed - {$result['error']}\n";
				
				// Show individual item errors if any
				if (!empty($result['errors']))
				{
					foreach (array_slice($result['errors'], 0, 5) as $err)
					{
						$text .= "    - {$err}\n";
					}
				}
			}
			$text .= "\n";
		}
		
		return $text;
	}

	/**
	 * Format statistics text
	 *
	 * @param array $stats
	 * @return string
	 */
	private function formatStats($stats)
	{
		$text = "=== Indexed Documents ===\n";
		$text .= "Total: {$stats['total']}\n";
		
		foreach ($stats['by_app'] as $app => $count)
		{
			$text .= "  " . ucfirst($app) . ": {$count}\n";
		}
		
		// Add source counts if Bo is available
		if ($this->bo)
		{
			try
			{
				$source_counts = $this->bo->getSourceCounts();
				$text .= "\n=== Available Source Data ===\n";
				$source_total = array_sum($source_counts);
				$text .= "Total: {$source_total}\n";
				foreach ($source_counts as $app => $count)
				{
					$text .= "  " . ucfirst($app) . ": {$count}\n";
				}
			}
			catch (\Exception $e)
			{
				// Ignore errors getting source counts
			}
		}
		
		return $text;
	}

	/**
	 * Check configuration status
	 *
	 * @param array $config
	 * @return string
	 */
	private function checkConfiguration($config)
	{
		$status = "Configuration Status:\n";
		$status .= "--------------------\n";
		
		// Embedding config
		$status .= "Embedding Provider: " . ($config['embedding_provider'] ?? 'NOT SET') . "\n";
		$status .= "Embedding API Key: " . (!empty($config['embedding_api_key']) ? '✓ SET (' . strlen($config['embedding_api_key']) . ' chars)' : '✗ NOT SET') . "\n";
		$status .= "Embedding API URL: " . (!empty($config['embedding_api_url']) ? '✓ ' . $config['embedding_api_url'] : '✗ NOT SET') . "\n";
		$status .= "Embedding Model: " . ($config['embedding_model'] ?? 'NOT SET') . "\n\n";
		
		// LLM config
		$status .= "LLM Provider: " . ($config['llm_provider'] ?? 'NOT SET') . "\n";
		$status .= "LLM API Key: " . (!empty($config['llm_api_key']) ? '✓ SET (' . strlen($config['llm_api_key']) . ' chars)' : '✗ NOT SET') . "\n";
		$status .= "LLM API URL: " . (!empty($config['llm_api_url']) ? '✓ ' . $config['llm_api_url'] : '✗ NOT SET') . "\n";
		$status .= "LLM Model: " . ($config['llm_model'] ?? 'NOT SET') . "\n";
		
		return $status;
	}

	/**
	 * Test embedding connection
	 *
	 * @return string Result text
	 */
	private function testEmbeddingConnection()
	{
		$result = "Testing Embedding API Connection...\n";
		$result .= "====================================\n\n";
		
		try
		{
			$config = Api\Config::read('amirrag');
			
			// Check required config
			if (empty($config['embedding_api_key']))
			{
				return $result . "✗ ERROR: Embedding API Key is not configured!\n\nPlease go to Admin → Site Configuration → amirrag and set your API key.";
			}
			
			if (empty($config['embedding_api_url']))
			{
				return $result . "✗ ERROR: Embedding API URL is not configured!\n\nPlease go to Admin → Site Configuration → amirrag and set your API URL.";
			}
			
			$result .= "Configuration:\n";
			$result .= "  Provider: " . ($config['embedding_provider'] ?? 'openai') . "\n";
			$result .= "  URL: " . $config['embedding_api_url'] . "\n";
			$result .= "  Model: " . ($config['embedding_model'] ?? 'text-embedding-ada-002') . "\n";
			$result .= "  API Key: " . substr($config['embedding_api_key'], 0, 10) . "..." . substr($config['embedding_api_key'], -5) . "\n\n";
			
			// Try to generate embedding
			$embedding_service = new EmbeddingService();
			$test_text = "This is a test to verify the embedding API connection.";
			
			$result .= "Sending test request...\n";
			$embedding = $embedding_service->embed($test_text);
			
			if (!empty($embedding) && is_array($embedding))
			{
				$result .= "\n✓ SUCCESS! Connection is working.\n";
				$result .= "  Embedding dimension: " . count($embedding) . "\n";
				$result .= "  First 5 values: [" . implode(', ', array_map(function($v) { return number_format($v, 6); }, array_slice($embedding, 0, 5))) . ", ...]\n";
			}
			else
			{
				$result .= "\n✗ ERROR: Received empty or invalid embedding response.\n";
			}
		}
		catch (\Exception $e)
		{
			$result .= "\n✗ ERROR: " . $e->getMessage() . "\n";
			$result .= "\nPossible causes:\n";
			$result .= "  1. Invalid API key\n";
			$result .= "  2. Wrong API URL\n";
			$result .= "  3. Model not available on your IONOS account\n";
			$result .= "  4. Network connectivity issues\n";
		}
		
		return $result;
	}

	/**
	 * Test connection via AJAX (public method)
	 */
	public function testConnection()
	{
		$result = $this->testEmbeddingConnection();
		
		Api\Json\Response::get()->data(['result' => $result]);
	}
}
