<?php

namespace serialnumberchecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SerialNumberTable extends \WP_List_Table
{
	private static $items_per_page = 50;
	
	public function get_columns()
	{
  		$columns = array(
    		'serial' 		=> 'Serial',
    		'customer'    	=> 'Customer',
  			'product_model' => 'Product Model',
  			'state' 		=> 'State',
  			'date_add'		=> 'Date Add',
  			'date_visited'	=> 'Date Visited',
			'date_manufactured' => 'Date Manufactured',
  		);
  		
  		return $columns;
	}
	
	public function prepare_items()
	{
		$columns = $this->get_columns();
		
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array($columns, $hidden, $sortable);
		
		$adapter = new Adapter\SerialDatabase();
		
		// If no sort, default to title
		$orderby = !empty($_GET['orderby'])? $_GET['orderby'] : 'serial';
		// If no order, default to asc
		$order = !empty($_GET['order'])? $_GET['order'] : 'asc';
		
		$orderby .= ' ' . $order;

		$current_page = $this->get_pagenum();
		$index = ($current_page - 1) * self::$items_per_page;
		
		$search = isset($_POST['s'])? trim($_POST['s']): null;
		
		$this->items = $adapter->getAll($orderby, $index, self::$items_per_page, $search);
		
		$total_items = $adapter->count_no_limit;
		
		$this->set_pagination_args( array(
			'total_items' => $adapter->count_no_limit,
			'per_page'    => self::$items_per_page
		) );
	}
	
	public function column_default($item, $column_name)
	{
		if (isset($item[$column_name]))
			return $item[$column_name];
		else
			return print_r( $item, true ) ; // Show the whole array for troubleshooting purposes
	}
	
	public function get_sortable_columns()
	{
		$sortable_columns = array(
			'serial'  => array('serial', true),
			'customer' => array('customer',false),
			'product_model'   => array('product_model',false),
			'date_add'   => array('date_add', false),
			'date_visited'   => array('date_visited', false),
		);
		
		return $sortable_columns;
	}
	
	public function column_date_add($item)
	{
		if (isset($item['date_add']))
			return date('d M Y H:i:s', strtotime($item['date_add']));
		else 
			return "";
	}
	
	public function column_date_visited($item)
	{
		if (isset($item['date_visited']))
			return date('d M Y H:i:s', strtotime($item['date_visited']));
		else
			return "";
	}
	
	public function column_state($item)
	{
		if (isset($item['state']))
		{
			if (SerialNumber::stateIsValid($item['state']))
				return '<strong style="color: Green;">Valid</strong>';
			elseif (SerialNumber::stateIsError($item['state']))
				return '<strong style="color: Red;">Invalid</strong>';
		}
		
		return "";
	}
	
	public function column_serial($item)
	{
		$actions = array(
			'edit'      => sprintf('<a href="?page=%s&action=%s&id=%s">Edit</a>',$_REQUEST['page'],'edit',$item['id']),
			'delete'    => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>',$_REQUEST['page'],'delete',$item['id']),
		);
	
		return sprintf('%1$s %2$s', $item['serial'], $this->row_actions($actions) );
	}
}
