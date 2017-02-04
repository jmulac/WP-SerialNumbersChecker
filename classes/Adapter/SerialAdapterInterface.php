<?php

namespace serialnumberchecker\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

interface SerialAdapterInterface
{
	public function load($id);
	public function loadBySerial($serial);
	public function getAll($index = 0, $limit = 0);
	public function update($id, $data);
	public function insert($data);
	public function delete($id);
}