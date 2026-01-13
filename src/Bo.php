<?php
/**
 * EGroupware AmirRAG - Business Object
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
 * AmirRAG Business Object
 * Core business logic for the RAG system
 * Uses direct database access for better performance and reliability
 */
class Bo
{
	/**
	 * @var Api\Db EGroupware database object
	 */
	private $db;
	
	/**
	 * @var DatabaseService
	 */
	private $db_service;
	
	/**
	 * @var EmbeddingService
	 */
	private $embedding_service;
	
	/**
	 * @var LLMService
	 */
	private $llm_service;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// Load composer autoloader for OpenAI client
		$autoload = __DIR__ . '/../vendor/autoload.php';
		if (file_exists($autoload))
		{
			require_once $autoload;
		}
		
		// Get database connection
		$this->db = $GLOBALS['egw']->db;
		
		try
		{
			$this->db_service = new DatabaseService();
			$this->embedding_service = new EmbeddingService();
			$this->llm_service = new LLMService();
		}
		catch (\Exception $e)
		{
			error_log('AmirRAG Bo initialization error: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Search using natural language query
	 *
	 * @param string $query Search query
	 * @param string $app_name Optional app filter
	 * @param bool $use_llm Whether to use LLM for response generation
	 * @return array Search results with optional LLM response
	 */
	public function search($query, $app_name = '', $use_llm = true)
	{
		try
		{
			// Generate embedding for query
			$query_embedding = $this->embedding_service->embed($query);
			
			// Search for similar documents
			$results = $this->db_service->search($query_embedding, $app_name, 5);
			
			$response = [
				'results' => $results,
				'query' => $query,
			];
			
			// Generate LLM response if requested and results found
			if ($use_llm && !empty($results))
			{
				try
				{
					$llm_response = $this->llm_service->generateResponse($query, $results);
					$response['llm_response'] = $llm_response;
				}
				catch (\Exception $e)
				{
					error_log('AmirRAG LLM generation error: ' . $e->getMessage());
					$response['llm_response'] = 'Unable to generate detailed response.';
				}
			}
			
			return $response;
		}
		catch (\Exception $e)
		{
			error_log('AmirRAG search error: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Index data from a specific app
	 *
	 * @param string $app_name Application name (addressbook, calendar, infolog)
	 * @param int $limit Number of records to index (0 = all)
	 * @return array Status information
	 */
	public function indexApp($app_name, $limit = 0)
	{
		$indexed = 0;
		$errors = [];
		
		try
		{
			switch ($app_name)
			{
				case 'addressbook':
					$indexed = $this->indexAddressbook($limit, $errors);
					break;
				case 'calendar':
					$indexed = $this->indexCalendar($limit, $errors);
					break;
				case 'infolog':
					$indexed = $this->indexInfolog($limit, $errors);
					break;
				default:
					throw new \Exception("Unsupported app: {$app_name}");
			}
			
			return [
				'success' => true,
				'app_name' => $app_name,
				'indexed' => $indexed,
				'errors' => $errors,
			];
		}
		catch (\Exception $e)
		{
			error_log('AmirRAG indexApp error: ' . $e->getMessage());
			return [
				'success' => false,
				'app_name' => $app_name,
				'indexed' => $indexed,
				'error' => $e->getMessage(),
				'errors' => $errors,
			];
		}
	}

	/**
	 * Index addressbook contacts using direct database access
	 *
	 * @param int $limit
	 * @param array &$errors
	 * @return int Number indexed
	 */
	private function indexAddressbook($limit, &$errors)
	{
		// Query contacts directly from egw_addressbook table
		// contact_tid 'D' = deleted, 'n' = normal contact, NULL = also normal
		$sql = "SELECT contact_id, n_prefix, n_given, n_family, n_fn, org_name, org_unit, 
				contact_title, contact_email, contact_email_home, tel_work, tel_cell, tel_home,
				adr_one_street, adr_one_locality, adr_one_region, adr_one_postalcode, adr_one_countryname,
				contact_note, contact_bday
				FROM egw_addressbook 
				WHERE (contact_tid IS NULL OR contact_tid = 'n' OR contact_tid != 'D')
				ORDER BY contact_modified DESC";
		
		if ($limit > 0)
		{
			$sql .= " LIMIT " . (int)$limit;
		}
		
		$indexed = 0;
		
		foreach ($this->db->query($sql, __LINE__, __FILE__) as $row)
		{
			try
			{
				$doc = $this->prepareContactFromDb($row);
				
				if ($doc && !empty($doc['content']))
				{
					$embedding = $this->embedding_service->embed($doc['content']);
					$this->db_service->insertDocument(
						$doc['id'],
						'addressbook',
						$doc['content'],
						$embedding,
						$doc['metadata']
					);
					$indexed++;
				}
			}
			catch (\Exception $e)
			{
				$errors[] = "Contact {$row['contact_id']}: " . $e->getMessage();
				error_log("AmirRAG indexAddressbook error for contact {$row['contact_id']}: " . $e->getMessage());
			}
		}
		
		return $indexed;
	}

	/**
	 * Index calendar events using direct database access
	 *
	 * @param int $limit
	 * @param array &$errors
	 * @return int Number indexed
	 */
	private function indexCalendar($limit, &$errors)
	{
		// Query calendar events directly from egw_cal table
		// Note: cal_start and cal_end are in egw_cal_dates, use range_start/range_end from egw_cal
		$sql = "SELECT c.cal_id, c.cal_title, c.cal_description, c.cal_location, 
				c.range_start, c.range_end, c.cal_priority, c.cal_public, c.cal_modified
				FROM egw_cal c
				WHERE c.cal_deleted IS NULL
				ORDER BY c.cal_modified DESC";
		
		if ($limit > 0)
		{
			$sql .= " LIMIT " . (int)$limit;
		}
		
		$indexed = 0;
		
		foreach ($this->db->query($sql, __LINE__, __FILE__) as $row)
		{
			try
			{
				$doc = $this->prepareEventFromDb($row);
				
				if ($doc && !empty($doc['content']))
				{
					$embedding = $this->embedding_service->embed($doc['content']);
					$this->db_service->insertDocument(
						$doc['id'],
						'calendar',
						$doc['content'],
						$embedding,
						$doc['metadata']
					);
					$indexed++;
				}
			}
			catch (\Exception $e)
			{
				$errors[] = "Event {$row['cal_id']}: " . $e->getMessage();
				error_log("AmirRAG indexCalendar error for event {$row['cal_id']}: " . $e->getMessage());
			}
		}
		
		return $indexed;
	}

	/**
	 * Index infolog entries using direct database access
	 *
	 * @param int $limit
	 * @param array &$errors
	 * @return int Number indexed
	 */
	private function indexInfolog($limit, &$errors)
	{
		// Query infolog entries directly from egw_infolog table
		// Note: info_addr doesn't exist, use info_location instead
		$sql = "SELECT info_id, info_subject, info_des, info_type, info_status, 
				info_priority, info_percent, info_datecompleted,
				info_startdate, info_enddate, info_from, info_location
				FROM egw_infolog 
				WHERE info_status != 'deleted'
				ORDER BY info_datemodified DESC";
		
		if ($limit > 0)
		{
			$sql .= " LIMIT " . (int)$limit;
		}
		
		$indexed = 0;
		
		foreach ($this->db->query($sql, __LINE__, __FILE__) as $row)
		{
			try
			{
				$doc = $this->prepareInfologFromDb($row);
				
				if ($doc && !empty($doc['content']))
				{
					$embedding = $this->embedding_service->embed($doc['content']);
					$this->db_service->insertDocument(
						$doc['id'],
						'infolog',
						$doc['content'],
						$embedding,
						$doc['metadata']
					);
					$indexed++;
				}
			}
			catch (\Exception $e)
			{
				$errors[] = "InfoLog {$row['info_id']}: " . $e->getMessage();
				error_log("AmirRAG indexInfolog error for entry {$row['info_id']}: " . $e->getMessage());
			}
		}
		
		return $indexed;
	}

	/**
	 * Prepare contact document from database row
	 *
	 * @param array $row Database row
	 * @return array Document data
	 */
	private function prepareContactFromDb($row)
	{
		$name = trim(($row['n_prefix'] ?? '') . ' ' . ($row['n_given'] ?? '') . ' ' . ($row['n_family'] ?? ''));
		if (empty(trim($name)))
		{
			$name = $row['n_fn'] ?? '';
		}
		
		$org = $row['org_name'] ?? '';
		$unit = $row['org_unit'] ?? '';
		$title = $row['contact_title'] ?? '';
		$email = $row['contact_email'] ?? $row['contact_email_home'] ?? '';
		$tel = $row['tel_work'] ?? $row['tel_cell'] ?? $row['tel_home'] ?? '';
		$notes = $row['contact_note'] ?? '';
		$bday = $row['contact_bday'] ?? '';
		
		// Build address
		$address_parts = array_filter([
			$row['adr_one_street'] ?? '',
			$row['adr_one_locality'] ?? '',
			$row['adr_one_region'] ?? '',
			$row['adr_one_postalcode'] ?? '',
			$row['adr_one_countryname'] ?? '',
		]);
		$address = implode(', ', $address_parts);
		
		// Build content for embedding
		$content = "Contact: {$name}\n";
		
		if ($org) $content .= "Organization: {$org}\n";
		if ($unit) $content .= "Department: {$unit}\n";
		if ($title) $content .= "Title: {$title}\n";
		if ($email) $content .= "Email: {$email}\n";
		if ($tel) $content .= "Phone: {$tel}\n";
		if ($address) $content .= "Address: {$address}\n";
		if ($bday) $content .= "Birthday: {$bday}\n";
		if ($notes) $content .= "Notes: {$notes}\n";
		
		return [
			'id' => (string)$row['contact_id'],
			'content' => $content,
			'metadata' => [
				'name' => $name,
				'org' => $org,
				'email' => $email,
			],
		];
	}

	/**
	 * Prepare calendar event document from database row
	 *
	 * @param array $row Database row
	 * @return array Document data
	 */
	private function prepareEventFromDb($row)
	{
		$title = $row['cal_title'] ?? '';
		$description = $row['cal_description'] ?? '';
		$location = $row['cal_location'] ?? '';
		// Use range_start and range_end (not cal_start/cal_end which are in egw_cal_dates)
		$start = $row['range_start'] ? date('Y-m-d H:i', $row['range_start']) : '';
		$end = $row['range_end'] ? date('Y-m-d H:i', $row['range_end']) : '';
		$priority = $row['cal_priority'] ?? '';
		
		// Build content for embedding
		$content = "Calendar Event: {$title}\n";
		
		if ($start) $content .= "Start: {$start}\n";
		if ($end) $content .= "End: {$end}\n";
		if ($location) $content .= "Location: {$location}\n";
		if ($priority) $content .= "Priority: {$priority}\n";
		if ($description) $content .= "Description: {$description}\n";
		
		return [
			'id' => (string)$row['cal_id'],
			'content' => $content,
			'metadata' => [
				'title' => $title,
				'start' => $start,
				'location' => $location,
			],
		];
	}

	/**
	 * Prepare infolog document from database row
	 *
	 * @param array $row Database row
	 * @return array Document data
	 */
	private function prepareInfologFromDb($row)
	{
		$subject = $row['info_subject'] ?? '';
		$description = $row['info_des'] ?? '';
		$type = $row['info_type'] ?? '';
		$status = $row['info_status'] ?? '';
		$priority = $row['info_priority'] ?? '';
		$percent = $row['info_percent'] ?? '';
		$from = $row['info_from'] ?? '';
		// Use info_location instead of info_addr (which doesn't exist)
		$location = $row['info_location'] ?? '';
		
		$startdate = $row['info_startdate'] ? date('Y-m-d', $row['info_startdate']) : '';
		$enddate = $row['info_enddate'] ? date('Y-m-d', $row['info_enddate']) : '';
		
		// Build content for embedding
		$content = "InfoLog: {$subject}\n";
		
		if ($type) $content .= "Type: {$type}\n";
		if ($status) $content .= "Status: {$status}\n";
		if ($priority) $content .= "Priority: {$priority}\n";
		if ($percent) $content .= "Completion: {$percent}%\n";
		if ($from) $content .= "From: {$from}\n";
		if ($location) $content .= "Location: {$location}\n";
		if ($startdate) $content .= "Start Date: {$startdate}\n";
		if ($enddate) $content .= "Due Date: {$enddate}\n";
		if ($description) $content .= "Description: {$description}\n";
		
		return [
			'id' => (string)$row['info_id'],
			'content' => $content,
			'metadata' => [
				'subject' => $subject,
				'type' => $type,
				'status' => $status,
			],
		];
	}

	/**
	 * Queue an item for indexing
	 *
	 * @param string $app_name
	 * @param string $item_id
	 * @return bool Success
	 */
	public function queueForIndexing($app_name, $item_id)
	{
		return $this->db_service->queueForIndexing($app_name, $item_id);
	}

	/**
	 * Get document statistics
	 *
	 * @return array Statistics
	 */
	public function getStatistics()
	{
		$counts = $this->db_service->getDocumentCounts();
		$total = array_sum($counts);
		
		return [
			'total' => $total,
			'by_app' => $counts,
		];
	}

	/**
	 * Get source data counts (available items to index)
	 *
	 * @return array App name => count
	 */
	public function getSourceCounts()
	{
		$counts = [];
		
		// Count addressbook contacts
		$sql = "SELECT COUNT(*) as cnt FROM egw_addressbook WHERE (contact_tid IS NULL OR contact_tid = 'n' OR contact_tid != 'D')";
		$row = $this->db->query($sql, __LINE__, __FILE__)->fetch();
		$counts['addressbook'] = (int)($row['cnt'] ?? 0);
		
		// Count calendar events
		$sql = "SELECT COUNT(*) as cnt FROM egw_cal WHERE cal_deleted IS NULL";
		$row = $this->db->query($sql, __LINE__, __FILE__)->fetch();
		$counts['calendar'] = (int)($row['cnt'] ?? 0);
		
		// Count infolog entries
		$sql = "SELECT COUNT(*) as cnt FROM egw_infolog WHERE info_status != 'deleted'";
		$row = $this->db->query($sql, __LINE__, __FILE__)->fetch();
		$counts['infolog'] = (int)($row['cnt'] ?? 0);
		
		return $counts;
	}

	/**
	 * Clear all indexed documents for current user
	 *
	 * @return bool Success
	 */
	public function clearIndex()
	{
		return $this->db_service->clearUserDocuments();
	}
}
