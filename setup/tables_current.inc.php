<?php
/**
 * EGroupware AmirRAG - Database Schema
 *
 * @package amirrag
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$phpgw_baseline = array(
	'egw_amirrag_documents' => array(
		'fd' => array(
			'doc_id' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'user_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'app_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'content' => array('type' => 'text','nullable' => False),
			'embedding' => array('type' => 'blob','nullable' => False),
			'metadata' => array('type' => 'text','nullable' => True),
			'created_at' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'updated_at' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
		),
		'pk' => array('doc_id','user_id','app_name'),
		'fk' => array(),
		'ix' => array(
			array('user_id'),
			array('app_name'),
			array('user_id','app_name'),
		),
		'uc' => array()
	),
	'egw_amirrag_index_queue' => array(
		'fd' => array(
			'queue_id' => array('type' => 'auto','nullable' => False),
			'user_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'app_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'item_id' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'action' => array('type' => 'varchar','precision' => '32','nullable' => False), // 'index' or 'delete'
			'status' => array('type' => 'varchar','precision' => '32','nullable' => False,'default' => 'pending'), // 'pending', 'processing', 'completed', 'failed'
			'error_message' => array('type' => 'text','nullable' => True),
			'created_at' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'processed_at' => array('type' => 'timestamp','nullable' => True),
		),
		'pk' => array('queue_id'),
		'fk' => array(),
		'ix' => array(
			array('user_id'),
			array('status'),
			array('app_name','item_id'),
		),
		'uc' => array()
	),
);
