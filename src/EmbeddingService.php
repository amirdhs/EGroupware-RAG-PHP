<?php
/**
 * EGroupware AmirRAG - Embedding Service
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
 * Embedding Service for Vector Generation
 * Supports OpenAI and IONOS embedding models
 */
class EmbeddingService
{
	/**
	 * @var string Provider (openai or ionos)
	 */
	private $provider;
	
	/**
	 * @var \GuzzleHttp\Client HTTP client
	 */
	private $httpClient;
	
	/**
	 * @var string API key
	 */
	private $apiKey;
	
	/**
	 * @var string API URL
	 */
	private $apiUrl;
	
	/**
	 * @var string Model name
	 */
	private $model;
	
	/**
	 * @var int Embedding dimension
	 */
	private $dimension;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Load composer autoloader
		$autoload = __DIR__ . '/../vendor/autoload.php';
		if (file_exists($autoload))
		{
			require_once $autoload;
		}
		
		// Read config from database
		$config = Api\Config::read('amirrag');
		
		// Debug: log config for troubleshooting
		error_log('AmirRAG EmbeddingService: Loading config...');
		error_log('AmirRAG EmbeddingService: Config keys found: ' . implode(', ', array_keys($config)));
		
		// Get config values with fallbacks
		$this->provider = $config['embedding_provider'] ?? 'ionos';
		$this->model = $config['embedding_model'] ?? 'BAAI/bge-m3';
		$this->apiKey = trim($config['embedding_api_key'] ?? '');
		$this->apiUrl = trim($config['embedding_api_url'] ?? '');
		
		// Validate required config
		if (empty($this->apiKey))
		{
			throw new \Exception(
				'Embedding API key not configured. ' .
				'Please go to Admin → Site Configuration → amirrag and enter your IONOS/OpenAI API key.'
			);
		}
		
		if (empty($this->apiUrl))
		{
			throw new \Exception(
				'Embedding API URL not configured. ' .
				'Please go to Admin → Site Configuration → amirrag and enter the API URL ' .
				'(e.g., https://openai.inference.de-txl.ionos.com/v1).'
			);
		}
		
		// Build the embeddings endpoint URL
		$this->apiUrl = rtrim($this->apiUrl, '/') . '/embeddings';
		
		error_log('AmirRAG EmbeddingService: Initialized with:');
		error_log('  - provider: ' . $this->provider);
		error_log('  - model: ' . $this->model);
		error_log('  - apiUrl: ' . $this->apiUrl);
		error_log('  - apiKey length: ' . strlen($this->apiKey));
		
		// Initialize HTTP client with Guzzle
		$this->httpClient = new \GuzzleHttp\Client([
			'timeout' => 60,
			'verify' => true,
			'http_errors' => false, // Don't throw exceptions on HTTP errors
		]);
		
		// Set dimension based on model
		$this->dimension = $this->getModelDimension();
	}

	/**
	 * Get embedding dimension for the current model
	 *
	 * @return int
	 */
	private function getModelDimension()
	{
		// Common model dimensions
		$dimensions = [
			// OpenAI models
			'text-embedding-ada-002' => 1536,
			'text-embedding-3-small' => 1536,
			'text-embedding-3-large' => 3072,
			// IONOS/HuggingFace models
			'BAAI/bge-m3' => 1024,
			'BAAI/bge-large-en-v1.5' => 1024,
			'sentence-transformers/all-MiniLM-L6-v2' => 384,
		];
		
		return $dimensions[$this->model] ?? 1024; // Default to 1024 for IONOS models
	}

	/**
	 * Generate embedding for a single text
	 *
	 * @param string $text
	 * @return array Vector as array of floats
	 */
	public function embed($text)
	{
		if (empty($text))
		{
			throw new \Exception('Cannot generate embedding for empty text');
		}
		
		// Truncate text if too long (most models have a token limit)
		$text = mb_substr(trim($text), 0, 8000);
		
		try
		{
			error_log('AmirRAG: Generating embedding for text of length ' . strlen($text));
			error_log('AmirRAG: Using model: ' . $this->model . ', provider: ' . $this->provider);
			
			if ($this->provider === 'ionos')
			{
				return $this->embedWithIONOS($text);
			}
			else
			{
				return $this->embedWithOpenAI($text);
			}
		}
		catch (\Exception $e)
		{
			error_log('AmirRAG Embedding Error: ' . $e->getMessage());
			error_log('AmirRAG Embedding Error Trace: ' . $e->getTraceAsString());
			throw new \Exception('Failed to generate embedding: ' . $e->getMessage());
		}
	}

	/**
	 * Generate embedding using IONOS API
	 *
	 * @param string $text
	 * @return array
	 */
	private function embedWithIONOS($text)
	{
		$payload = [
			'model' => $this->model,
			'input' => $text,
		];
		
		error_log('AmirRAG IONOS: Sending request to ' . $this->apiUrl);
		
		$response = $this->httpClient->post($this->apiUrl, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->apiKey,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
			'json' => $payload,
		]);
		
		$statusCode = $response->getStatusCode();
		$body = $response->getBody()->getContents();
		
		error_log('AmirRAG IONOS: Response status: ' . $statusCode);
		
		// Handle HTTP errors
		if ($statusCode === 401)
		{
			error_log('AmirRAG IONOS: Authentication failed. Body: ' . $body);
			throw new \Exception(
				'Authentication failed (401 Unauthorized). ' .
				'Please check your API key in Admin → Site Configuration → amirrag. ' .
				'Make sure you are using a valid IONOS API key.'
			);
		}
		
		if ($statusCode === 403)
		{
			throw new \Exception(
				'Access forbidden (403). Your API key may not have access to the embedding endpoint. ' .
				'Check your IONOS account permissions.'
			);
		}
		
		if ($statusCode === 404)
		{
			throw new \Exception(
				'Endpoint not found (404). Please verify your API URL is correct: ' . $this->apiUrl
			);
		}
		
		if ($statusCode !== 200)
		{
			throw new \Exception('IONOS API error: HTTP ' . $statusCode . ' - ' . $body);
		}
		
		$data = json_decode($body, true);
		
		if (json_last_error() !== JSON_ERROR_NONE)
		{
			throw new \Exception('Failed to parse IONOS response: ' . json_last_error_msg());
		}
		
		// IONOS response format: { "data": [{ "embedding": [...], "index": 0 }], "model": "...", "usage": {...} }
		if (isset($data['data'][0]['embedding']))
		{
			return $data['data'][0]['embedding'];
		}
		
		// Alternative format: { "embeddings": [[...]] }
		if (isset($data['embeddings'][0]))
		{
			return $data['embeddings'][0];
		}
		
		// Another format: { "embedding": [...] }
		if (isset($data['embedding']))
		{
			return $data['embedding'];
		}
		
		error_log('AmirRAG IONOS: Unknown response format: ' . json_encode(array_keys($data)));
		throw new \Exception('Unknown IONOS response format. Keys: ' . implode(', ', array_keys($data)));
	}

	/**
	 * Generate embedding using OpenAI API
	 *
	 * @param string $text
	 * @return array
	 */
	private function embedWithOpenAI($text)
	{
		$payload = [
			'model' => $this->model,
			'input' => $text,
		];
		
		$response = $this->httpClient->post($this->apiUrl, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->apiKey,
				'Content-Type' => 'application/json',
			],
			'json' => $payload,
		]);
		
		$statusCode = $response->getStatusCode();
		$body = $response->getBody()->getContents();
		
		if ($statusCode !== 200)
		{
			throw new \Exception('OpenAI API error: HTTP ' . $statusCode . ' - ' . $body);
		}
		
		$data = json_decode($body, true);
		
		if (json_last_error() !== JSON_ERROR_NONE)
		{
			throw new \Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
		}
		
		if (!isset($data['data'][0]['embedding']))
		{
			throw new \Exception('Invalid OpenAI response format');
		}
		
		return $data['data'][0]['embedding'];
	}

	/**
	 * Generate embeddings for multiple texts in batch
	 *
	 * @param array $texts Array of text strings
	 * @return array Array of vectors
	 */
	public function embedBatch($texts)
	{
		if (empty($texts))
		{
			return [];
		}
		
		// For now, process one at a time (can be optimized later for batch support)
		$embeddings = [];
		foreach ($texts as $text)
		{
			$embeddings[] = $this->embed($text);
		}
		
		return $embeddings;
	}

	/**
	 * Get the embedding dimension
	 *
	 * @return int
	 */
	public function getDimension()
	{
		return $this->dimension;
	}

	/**
	 * Get the current provider
	 *
	 * @return string
	 */
	public function getProvider()
	{
		return $this->provider;
	}

	/**
	 * Get the current model
	 *
	 * @return string
	 */
	public function getModel()
	{
		return $this->model;
	}
}
