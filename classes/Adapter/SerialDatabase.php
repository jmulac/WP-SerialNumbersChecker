<?php 

namespace serialnumberchecker\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class SerialDatabase implements SerialAdapterInterface
{
	private static $table_name = "serial_numbers";
	
	public $count_no_limit;
	
	public static function install()
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . self::$table_name;
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE $table_name (
		id int(9) NOT NULL AUTO_INCREMENT,
		serial varchar(100) NOT NULL,
		customer varchar(255) DEFAULT '' NOT NULL,
		product_model varchar(255) DEFAULT '' NOT NULL,
		date_add datetime NULL DEFAULT NULL,
		ip_visited varchar(15) NULL DEFAULT NULL,
		date_visited datetime NULL DEFAULT NULL,
		state  smallint(2) NOT NULL,
		PRIMARY KEY  (id),
		KEY serial (serial),
		UNIQUE KEY serialU (serial)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		add_option("snc_db_version", "1.0");
	}
	
	public static function uninstall()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql = "DROP TABLE $table_name;";
		$wpdb->query($sql);
		
		delete_option("snc_db_version");
	}
	
	public function load($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql = $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id);
		$data = $wpdb->get_row($sql, ARRAY_A);
		
		return $data;
	}
	
	public function loadBySerial($serial)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql = $wpdb->prepare("SELECT * FROM $table_name WHERE serial = %s", $serial);
		$data = $wpdb->get_row($sql, ARRAY_A);
		
		return $data;
	}
	
	public function getAll($orderby = null, $index = 0, $limit = 0, $search = null)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name";
		
		if (!empty($search))
			$sql .= " WHERE serial like '%$search%' OR customer like '%$search%' OR product_model like '%$search%'";
		
		if (!empty($orderby))
			$sql .= " ORDER BY $orderby";
		
		if ($limit > 0)
			$sql .= " LIMIT $index, $limit";
		
		$data = $wpdb->get_results($sql, ARRAY_A);
		
		$this->generate_count_no_limit();
		
		return $data;
	}
	
	public function getAllBySerials($serials, $use_serial_as_key = true)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql = "SELECT * FROM $table_name where serial IN ('".implode("', '", $serials)."')";
		
		$data = $wpdb->get_results($sql, ARRAY_A);
		
		if ($use_serial_as_key)
		{
			$tmp = array();
			foreach ($data as $d)
				$tmp[$d['serial']] = $d;
			$data = $tmp;
		}
		
		return $data;
	}
	
	protected function generate_count_no_limit()
	{
		global $wpdb;
		
		// Get total results number
		$sql = ' SELECT FOUND_ROWS() AS Count ';
		
		$data = $wpdb->get_row($sql, ARRAY_A);
		$this->count_no_limit = $data['Count'];
	}
	
	public function update($id, $data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$updated = $wpdb->update( $table_name, $data, array('id' => $id) );
		return $updated !== false;
	}
	
	public function updateItems($data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		foreach ($data as $d)
		{
			$updated = $wpdb->update( $table_name, $d, array('id' => $d['id']) );
		}
	}
	
	public function insert($data)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		
		$sql_data = array(
			'serial' => $data['serial'],
			'customer' => isset($data['customer'])? $data['customer']: "",
			'product_model' => isset($data['product_model'])? $data['product_model']: "",
			'date_add' => date('Y-m-d H:i:s'),
			'state' => $data['state'],
		);
		
		$inserted = $wpdb->insert($table_name, $sql_data, array('%s', '%s', '%s', '%s', '%d'));
		return $inserted !== false;
	}
	
	public function delete($id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		return $wpdb->delete($table_name, array('id' => (int)$id));
	}
}