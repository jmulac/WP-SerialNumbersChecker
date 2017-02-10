<?php

namespace serialnumberchecker\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface SerialAdapterInterface
{	
	public function load($id);
	public function loadBySerial($serial);
	public function getAll($orderby = null, $index = 0, $limit = 0, $search = null);
	public function getAllBySerials($serials, $use_serial_as_key = true);
	public function update($id, $data);
	public function updateItems($data);
	public function insert($data);
	public function delete($id);
}