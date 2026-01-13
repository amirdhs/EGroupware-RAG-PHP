<?php
/**
 * EGroupware AmirRAG - Database Service
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
 * Database Service for Vector Storage and Retrieval
 * Handles all vector storage and search operations using MariaDB
 */
class DatabaseService
{
	/**
	 * @var Api\Db EGroupware database object
	 */
	private $db;
	
	/**
	 * @var string Current user ID
	 */
	private $user_id;
	
	/**
	 * @var string Documents table name
	 */
	private $docs_table = 'egw_amirrag_documents';
	
	/**
	 * @var string Queue table name
	 */
	private $queue_table = 'egw_amirrag_index_queue';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Clone the database object and set app context for table definitions
		$this->db = clone($GLOBALS['egw']->db);
		$this->db->set_app('amirrag');
		$this->user_id = $GLOBALS['egw_info']['user']['account_id'];
	}

	/**
	 * Set the current user ID for all operations
	 *
	 * @param string $user_id
	 */
	public function setUserId($user_id)
	{
		$this->user_id = $user_id;
	}

	/**
	 * Serialize embedding vector to binary format
	 *
	 * @param array $embedding Vector as array of floats
	 * @return string Binary representation
	 */
	private function serializeEmbedding($embedding)
	{
		return serialize($embedding);
	}

	/**
	 * Deserialize embedding vector from binary format
	 *
	 * @param string $data Binary data
	 * @return array Vector as array of floats
	 */
	private function deserializeEmbedding($data)
	{
		return unserialize($data);
	}

	/**
	 * Insert or update a document
	 *
	 * @param string $doc_id Document ID
	 * @param string $app_name Application name
	 * @param string $content Document content
	 * @param array $embedding Vector embedding
	 * @param array $metadata Additional metadata
	 * @return bool Success
	 */
	public function insertDocument($doc_id, $app_name, $content, $embedding, $metadata = [])
	{
		$embedding_blob = $this->serializeEmbedding($embedding);
		$metadata_json = json_encode($metadata);
		
		$data = [
			'doc_id' => (string)$doc_id,
			'user_id' => (string)$this->user_id,
			'app_name' => (string)$app_name,
			'content' => (string)$content,
			'embedding' => $embedding_blob,
			'metadata' => $metadata_json,
			'updated_at' => date('Y-m-d H:i:s'),
		];
		
		// Check if document exists
		$exists = $this->db->select($this->docs_table, 'doc_id', [
			'doc_id' => (string)$doc_id,
			'user_id' => (string)$this->user_id,
			'app_name' => (string)$app_name,
		], __LINE__, __FILE__)->fetch();
		
		if ($exists)
		{
			// Update
			return $this->db->update($this->docs_table, $data, [
				'doc_id' => (string)$doc_id,
				'user_id' => (string)$this->user_id,
				'app_name' => (string)$app_name,
			], __LINE__, __FILE__);
		}
		else
		{
			// Insert
			$data['created_at'] = date('Y-m-d H:i:s');
			return $this->db->insert($this->docs_table, $data, false, __LINE__, __FILE__);
		}
	}

	/**
	 * Delete a document
	 *
	 * @param string $doc_id Document ID
	 * @param string $app_name Application name
	 * @return bool Success
	 */
	public function deleteDocument($doc_id, $app_name)
	{
		return $this->db->delete($this->docs_table, [
			'doc_id' => $doc_id,
			'user_id' => $this->user_id,
			'app_name' => $app_name,
		], __LINE__, __FILE__);
	}

	/**
	 * Search for similar documents using cosine similarity
	 *
	 * @param array $query_embedding Query vector
	 * @param string $app_name Optional app name filter
	 * @param int $top_k Number of results to return
	 * @return array Array of documents with similarity scores
	 */
	public function search($query_embedding, $app_name = '', $top_k = 5)
	{
		// Get all documents for the user
		$where = ['user_id' => $this->user_id];
		
		if (!empty($app_name))
		{
			$where['app_name'] = $app_name;
		}
		
		$rs = $this->db->select($this->docs_table, '*', $where, __LINE__, __FILE__);
		
		$results = [];
		
		foreach ($rs as $row)
		{
			$doc_embedding = $this->deserializeEmbedding($row['embedding']);
			
			// Calculate cosine similarity
			$similarity = $this->cosineSimilarity($query_embedding, $doc_embedding);
			
			$results[] = [
				'doc_id' => $row['doc_id'],
				'app_name' => $row['app_name'],
				'content' => $row['content'],
				'metadata' => json_decode($row['metadata'], true),
				'similarity' => $similarity,
			];
		}
		
		// Sort by similarity (descending)
		usort($results, function($a, $b) {
			return $b['similarity'] <=> $a['similarity'];
		});
		
		// Return top K results
		return array_slice($results, 0, $top_k);
	}

	/**
	 * Calculate cosine similarity between two vectors
	 *
	 * @param array $a First vector
	 * @param array $b Second vector
	 * @return float Similarity score (0-1)
	 */
	private function cosineSimilarity($a, $b)
	{
		if (count($a) !== count($b))
		{
			return 0.0;
		}
		
		$dot_product = 0.0;
		$norm_a = 0.0;
		$norm_b = 0.0;
		
		for ($i = 0; $i < count($a); $i++)
		{
			$dot_product += $a[$i] * $b[$i];
			$norm_a += $a[$i] * $a[$i];
			$norm_b += $b[$i] * $b[$i];
		}
		
		$norm_a = sqrt($norm_a);
		$norm_b = sqrt($norm_b);
		
		if ($norm_a == 0.0 || $norm_b == 0.0)
		{
			return 0.0;
		}
		
		return $dot_product / ($norm_a * $norm_b);
	}

	/**
	 * Get document count by app
	 *
	 * @return array App name => count
	 */
	public function getDocumentCounts()
	{
		$rs = $this->db->select($this->docs_table, 'app_name, COUNT(*) as count', [
			'user_id' => $this->user_id,
		], __LINE__, __FILE__, false, 'GROUP BY app_name');
		
		$counts = [];
		foreach ($rs as $row)
		{
			$counts[$row['app_name']] = intval($row['count']);
		}
		
		return $counts;
	}

	/**
	 * Queue an item for indexing
	 *
	 * @param string $app_name Application name
	 * @param string $item_id Item ID
	 * @param string $action Action (index or delete)
	 * @return bool Success
	 */
	public function queueForIndexing($app_name, $item_id, $action = 'index')
	{
		return $this->db->insert($this->queue_table, [
			'user_id' => $this->user_id,
			'app_name' => $app_name,
			'item_id' => $item_id,
			'action' => $action,
			'status' => 'pending',
			'created_at' => date('Y-m-d H:i:s'),
		], false, __LINE__, __FILE__);
	}

	/**
	 * Get pending queue items
	 *
	 * @param int $limit Maximum number of items
	 * @return array Queue items
	 */
	public function getPendingQueue($limit = 10)
	{
		return $this->db->select($this->queue_table, '*', [
			'status' => 'pending',
		], __LINE__, __FILE__, false, 'ORDER BY created_at ASC', false, $limit)->fetchAll();
	}

	/**
	 * Update queue item status
	 *
	 * @param int $queue_id Queue ID
	 * @param string $status New status
	 * @param string $error_message Optional error message
	 * @return bool Success
	 */
	public function updateQueueStatus($queue_id, $status, $error_message = '')
	{
		$data = [
			'status' => $status,
			'processed_at' => date('Y-m-d H:i:s'),
		];
		
		if (!empty($error_message))
		{
			$data['error_message'] = $error_message;
		}
		
		return $this->db->update($this->queue_table, $data, [
			'queue_id' => $queue_id,
		], __LINE__, __FILE__);
	}

	/**
	 * Clear all documents for a user
	 *
	 * @return bool Success
	 */
	public function clearUserDocuments()
	{
		return $this->db->delete($this->docs_table, [
			'user_id' => $this->user_id,
		], __LINE__, __FILE__);
	}
}
