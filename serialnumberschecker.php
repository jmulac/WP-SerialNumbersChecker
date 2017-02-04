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
		
		// Add some options
		add_option("snc_contact_url", "");
		add_option("snc_contact_email", "");
	}
	
	public static function deactivate()
	{
		\serialnumberchecker\Adapter\SerialDatabase::uninstall();
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
		}
		
		if ($show_list)
			self::showSerialList();
	}
	
	public static function showSerialList()
	{
		$myListTable = new \serialnumberchecker\SerialNumberTable();
		echo '<div class="wrap"><h2>Serial List Table<small> - <a href="'.admin_url( 'admin.php?page=serial_list&action=add' ).'">Add Serial</a> - <a href="'.admin_url( 'admin.php?page=serial_list&action=export' ).'">Export Data</a></small></h2>';
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
	
	public static function exportFile()
	{
		if (!isset($_GET['page']) || $_GET['page'] != 'serial_list' || !isset($_GET['action']) || $_GET['action'] != 'export')
			return;
		
		$adapter = new \serialnumberchecker\Adapter\SerialDatabase();
		$data = $adapter->getAll();

		$columns = array(
			'Serial',
			'Customer',
			'Product Model',
			'State',
			'Date Add',
			'Date Visited',
			'IP Visited'
		);
		
		$csv_data = array();
		foreach ($data as $values)
		{
			$csv_data[] = array(
				$values['serial'],
				$values['customer'],
				$values['product_model'],
				$values['state'],
				$values['date_add'],
				$values['date_visited'],
				$values['ip_visited'],
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