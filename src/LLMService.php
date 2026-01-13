<?php
/**
 * EGroupware AmirRAG - LLM Service
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Amirrag;

use EGroupware\Api;
use OpenAI\Client as OpenAIClient;

/**
 * LLM Service for Response Generation
 * Uses OpenAI or IONOS API to generate natural language responses
 */
class LLMService
{
	/**
	 * @var string Provider (openai or ionos)
	 */
	private $provider;
	
	/**
	 * @var OpenAIClient OpenAI client
	 */
	private $client;
	
	/**
	 * @var string Model name
	 */
	private $model;
	
	/**
	 * @var float Temperature
	 */
	private $temperature;
	
	/**
	 * @var int Max tokens
	 */
	private $max_tokens;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$config = Api\Config::read('amirrag');
		
		$this->provider = $config['llm_provider'] ?? 'openai';
		$this->model = $config['llm_model'] ?? 'gpt-3.5-turbo';
		$this->temperature = floatval($config['llm_temperature'] ?? 0.3);
		$this->max_tokens = intval($config['llm_max_tokens'] ?? 600);
		
		$api_key = $config['llm_api_key'] ?? '';
		$api_url = $config['llm_api_url'] ?? '';
		
		if (empty($api_key))
		{
			throw new \Exception('LLM API key not configured');
		}
		
		// Initialize OpenAI client
		$factory = \OpenAI::factory()
			->withApiKey($api_key)
			->withHttpClient(new \GuzzleHttp\Client([
				'timeout' => 60,
				'verify' => true,
			]));
		
		// For IONOS, we need to set the base URL
		if ($this->provider === 'ionos' && !empty($api_url))
		{
			$factory = $factory->withBaseUri($api_url);
		}
		
		$this->client = $factory->make();
	}

	/**
	 * Generate a response based on context and query
	 *
	 * @param string $query User query
	 * @param array $context Relevant context documents
	 * @return string Generated response
	 */
	public function generateResponse($query, $context)
	{
		// Build context string
		$context_text = $this->buildContextText($context);
		
		// Create system prompt
		$system_prompt = "You are a helpful assistant that answers questions based on the provided context from an EGroupware system. ";
		$system_prompt .= "Use the context to provide accurate and relevant answers. ";
		$system_prompt .= "If the context doesn't contain enough information to answer the question, say so clearly.";
		
		// Create user prompt
		$user_prompt = "Context:\n\n" . $context_text . "\n\n";
		$user_prompt .= "Question: " . $query . "\n\n";
		$user_prompt .= "Please provide a helpful answer based on the context above.";
		
		try
		{
			$response = $this->client->chat()->create([
				'model' => $this->model,
				'messages' => [
					['role' => 'system', 'content' => $system_prompt],
					['role' => 'user', 'content' => $user_prompt],
				],
				'temperature' => $this->temperature,
				'max_tokens' => $this->max_tokens,
			]);
			
			return $response->choices[0]->message->content;
		}
		catch (\Exception $e)
		{
			error_log('AmirRAG LLM Error: ' . $e->getMessage());
			
			// Return a simple response if LLM fails
			return $this->generateSimpleResponse($query, $context);
		}
	}

	/**
	 * Build context text from documents
	 *
	 * @param array $context Array of documents with content
	 * @return string
	 */
	private function buildContextText($context)
	{
		$texts = [];
		
		foreach ($context as $doc)
		{
			$app = $doc['app_name'] ?? 'unknown';
			$content = $doc['content'] ?? '';
			
			if (!empty($content))
			{
				$texts[] = "[{$app}] {$content}";
			}
		}
		
		return implode("\n\n", $texts);
	}

	/**
	 * Generate a simple response without LLM (fallback)
	 *
	 * @param string $query
	 * @param array $context
	 * @return string
	 */
	private function generateSimpleResponse($query, $context)
	{
		if (empty($context))
		{
			return "I couldn't find any relevant information for your query.";
		}
		
		$response = "Based on the available data:\n\n";
		
		foreach (array_slice($context, 0, 3) as $idx => $doc)
		{
			$app = $doc['app_name'] ?? 'unknown';
			$content = $doc['content'] ?? '';
			
			if (!empty($content))
			{
				$response .= ($idx + 1) . ". [{$app}] " . substr($content, 0, 200) . "...\n\n";
			}
		}
		
		return $response;
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
