<?php 
/*
Plugin Name: Serial Numbers
Description: Serial numbers management
Version:     1
Author:      Julien Mulac
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists('SerialNumbersChecker')):

class SerialNumbersChecker
{
	private static $template_path = "html/";
	private static $table_name = "serial_numbers";
	
	// Import
	private static $import_file = "data/serial.csv";
	private static $serial_key = 1;
	
	public function init()
	{
		$this->includes();
		
		$this->createShortcodes();
		
		// Add action links
		add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), array($this, 'action_links'), 10, 2);
	}
	
	private function includes()
	{
		include_once( 'classes/Utils.php' );
		include_once( 'classes/SerialNumber.php' );
		include_once( 'classes/Adapter/SerialAdapterInterface.php' );
		include_once( 'classes/Adapter/SerialDatabase.php' );
		include_once( 'classes/SerialNumberTable.php' );
	}
	
	public static function activate()
	{
		// Install SQL Table
		\serialnumberchecker\Adapter\SerialDatabase::install();
		
		// Add some options TODO
		add_option("snc_contact_url", "http://www.veldt.xyz/en/contact/");
		add_option("snc_contact_email", "contact@veldt.xyz");
		
		// Create Page
		$postarr = array(
			'post_content' => "[snc_result get=serial/]",
			'post_title' => "Serial Check",
			'post_name' => 'check',
			'post_status' => 'publish',
			'post_type' => 'page',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
		);
		
		$id = wp_insert_post($postarr);
		if ($id > 0)
			add_option("snc_post_id", $id);
	}
	
	public static function deactivate()
	{
		\serialnumberchecker\Adapter\SerialDatabase::uninstall();
		
		$post_id = (int)get_option('snc_post_id', 0);
		if ($post_id > 0)
			wp_delete_post($post_id, true);
		
		delete_option("snc_contact_url");
		delete_option("snc_contact_email");
		delete_option("snc_post_id");
	}
	
	public function action_links($links, $file)
	{
		// Add settings link for this plugin
		// array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=snc_settings' ) . '">' . __( 'Settings' ) . '</a>' );
		
		return $links;
	}
	
	public function runCheck($serial_number)
	{
		$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
		$serial = new \serialnumberchecker\SerialNumber(0, $adapter);
		$serial->loadBySerial($serial_number);
		$serial->setVisited();
		
		return ($serial->isLoaded() && $serial->isValid())? $serial: false;
	}
	
	private function createShortcodes()
	{
		add_shortcode('snc_result', array($this, 'sc_result'));
	}
	
	public function sc_result($atts)
	{
		$atts = shortcode_atts(array(
			'get_key' => 'serial',
		), $atts);

		$serial = !empty($_GET[$atts['get_key']])? trim($_GET[$atts['get_key']]): "";
		if (empty($serial))
			return "";
		
		$contact_url = get_option('snc_contact_url');
		if (empty($contact_url))
			unset($contact_url);
		
		$contact_email = get_option('snc_contact_email');
		if (empty($contact_email))
			unset($contact_email);
		
		$serial = $this->runCheck($serial);
		
		$template_file = ($serial !== false)? 'serial_success.php': 'serial_error.php';
		
		ob_start();
		include self::$template_path . $template_file;
		return ob_get_clean();
	}
	
	/**
	 * ADMIN PART
	 */
	
	public static function my_add_menu_items()
	{
		add_menu_page( 'Serial List Table', 'Serial List Table', 'activate_plugins', 'serial_list', array('SerialNumbersChecker', 'render_serial_list'));
	}
	
	public static function render_serial_list()
	{
		// check action : edit / delete / add
		$action = isset($_GET['action'])? $_GET['action']: null;
		$show_list = true;
		$id = isset($_GET['id'])? (int)$_GET['id']: 0;
		
		switch ($action)
		{
			case 'edit': case 'add':
				self::showSerialForm($id);
				$show_list = false;
				break;
			case 'delete':
				self::showDelete($id);
				break;
			case 'export':
				self::exportFile();
				$show_list = false;
				break;
			case 'import':
				self::importFile();
				break;
		}
		
		if ($show_list)
			self::showSerialList();
	}
	
	public static function showSerialList()
	{
		$myListTable = new \serialnumberchecker\SerialNumberTable();
		echo '<div class="wrap"><h2>Serial List Table<small> - <a href="'.admin_url( 'admin.php?page=serial_list&action=add' ).'">Add Serial</a> - <a href="'.admin_url( 'admin.php?page=serial_list&action=import' ).'">Import File</a> - <a href="'.admin_url( 'admin.php?page=serial_list&action=export' ).'">Export Data</a></small></h2>';
		$myListTable->prepare_items();
		echo '<form method="post">
		<input type="hidden" name="page" value="serial_list" />';
		$myListTable->search_box('Recherche', 'search_id');
		echo '</form>';
		$myListTable->display();
		echo '</div>';
	}
	
	public static function showSerialForm($id = 0)
	{
		$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
		$serial = new \serialnumberchecker\SerialNumber($id, $adapter);
		
		$list_url = '<a href="'.admin_url( 'admin.php?page=serial_list' ).'">Go back to the table</a>';
		
		// if this fails, check_admin_referer() will automatically print a "failed" page and die.
		if ( ! empty( $_POST ) && check_admin_referer('serial-form', 'secu' ) )
		{
			// process form data
			$serial->init($_POST);
			if (($error = $serial->checkData()) !== true)
			{
				echo '<div class="updated error"><p>'.$error.'</p></div>';
			} else {
				
				$result = $serial->save();
				if ($result)
					echo '<div class="updated"><p>Update done ! '.$list_url.'</p></div>';
				else
					echo '<div class="updated error"><p>Update failed - '.$list_url.'</p></div>';
			}
		}
		
		echo $serial->getAdminHTMLForm();
		
		echo '<br><p>'.$list_url.'</p>';
	}
	
	public static function showDelete($id)
	{
		$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
		$serial = new \serialnumberchecker\SerialNumber($id, $adapter);
		$result = $serial->delete();
		
		if ($result)
			echo '<div class="updated"><p>Delete done !</p></div>';
		else
			echo '<div class="updated error"><p>Delete failed</p></div>';
	}
	
	public static function importFile()
	{
		$row = 0;
		$all_data = array();
		if (($handle = fopen(plugin_dir_path( __FILE__ ) . self::$import_file, "r")) !== FALSE)
		{
			while (($data = fgetcsv($handle, 0, ";")) !== FALSE)
			{
				$row++;
				if ($row == 1 || !isset($data[self::$serial_key]))
					continue;
				
				$all_data[trim($data[self::$serial_key])] = $data;
			}
			
			fclose($handle);
		}

		if (!empty($all_data))
		{
			// Get existing serials
			$serials = array_keys($all_data);
			$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
			$existing_data = $adapter->getAllBySerials($serials);

			// Update existing
			$update_data = array();
			if (!empty($existing_data))
			{
				$existing_serials = array_keys($existing_data);
				$update_serials = array_intersect($existing_serials, $serials);
				foreach ($update_serials as $serial)
				{
					$date_manufactured = isset($all_data[$serial][4])? $all_data[$serial][4]: "";
					
					// State
					$state = 1;
					$state_row = isset($all_data[$serial][5])? $all_data[$serial][5]: "";
					$state_row = trim($state_row);
					if ($state_row != "")
						$state = (int)$state_row;
					
					if ($existing_data[$serial]['customer'] == $all_data[$serial][3] && 
						$existing_data[$serial]['product_model'] == $all_data[$serial][2] &&
						$existing_data[$serial]['date_manufactured'] == $date_manufactured && 
						$existing_data[$serial]['state'] == $state)
						continue;
					
					$tmp = array(
						'customer' => $all_data[$serial][3],
						'product_model' => $all_data[$serial][2],
						'id' => $existing_data[$serial]['id'],
						'date_manufactured' => $date_manufactured,
						'state' => $state,
					);
						
					$update_data[] = $tmp;
				}

				$adapter->updateItems($update_data);

				$new_serials = array_diff($serials, $existing_serials);
			} else {
				$new_serials = array_keys($all_data);
			}
			
			// Add missing
			foreach ($new_serials as $serial)
			{
				$state = isset($all_data[$serial][5])? (int)$all_data[$serial][5]: 1;
				
				$tmp = array(
					'serial' => $serial,
					'customer' => $all_data[$serial][3],
					'product_model' => $all_data[$serial][2],
					'date_manufactured' => isset($all_data[$serial][4])? $all_data[$serial][4]: "",
					'state' => $state,
				);
				$adapter->insert($tmp);
			}
			
			// Display stats
			echo '<div class="updated"><p>
				Import results :<br>
				New data : '.count($new_serials).'<br>
				Updated data : '.count($update_data).'<br>
			</p></div>';
		} else
			echo '<div class="updated error"><p>No data to import !</p></div>';
	}
	
	public static function exportFile()
	{
		if (!isset($_GET['page']) || $_GET['page'] != 'serial_list' || !isset($_GET['action']) || $_GET['action'] != 'export')
			return;

		$page_post_id = (int)get_option("snc_post_id", 0);
		$page_url = ($page_post_id > 0)? get_permalink($page_post_id): "";

		$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
		$data = $adapter->getAll();

		$columns = array(
			'NFC',
			'Serial',
			'Helmet',
			'Customer Name',
			'Date of manufacture',
			'State',
		);
		
		$csv_data = array();
		foreach ($data as $values)
		{
			if (!empty($page_url))
				$serial_url = add_query_arg( 'serial', trim($values['serial']), $page_url);

			$csv_data[] = array(
				isset($serial_url)? $serial_url: "",
				$values['serial'],
				$values['product_model'],
				$values['customer'],
				isset($values['date_manufactured'])? $values['date_manufactured']: "",
				$values['state'],
			);
		}
		unset($data);
		
		// Generate CSV String
		$csv = '';
		$count = count($columns);
		$i = 0;
		foreach ($columns as $header)
		{
			if ($i > 0)
				$csv .= ';';
		
			$csv .= stripslashes(str_replace(';', ' ', $header));
			$i++;
		}
		$csv .= "\n";
		
		foreach($csv_data as $row)
		{
			$count = count($row);
			$i = 0;
		
			foreach ($columns as $key => $header)
			{
				if ($i > 0)
					$csv .= ';';
		
				$csv .= isset($row[$key])? stripslashes(str_replace(';', ' ', $row[$key])): '';
				$i++;
			}
		
			$csv .= "\n";
		}
		
		$csv .= "\n";

		$filename = 'export-' . date('d/m/Y_H-i-s') . '.csv';
		
		header("Content-type: application/vnd.ms-excel");
		header("Content-disposition: attachment; filename=\"$filename\"");
		print($csv);
		exit();
	}
}

endif;

register_activation_hook( __FILE__, array('SerialNumbersChecker', 'activate'));
register_deactivation_hook( __FILE__, array('SerialNumbersChecker', 'deactivate'));

add_action( 'admin_menu', array('SerialNumbersChecker', 'my_add_menu_items') );
add_action( 'admin_init', array('SerialNumbersChecker', 'exportFile') );

$class = new SerialNumbersChecker();
$class->init();
