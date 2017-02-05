<?php 

namespace serialnumberchecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class SerialNumber
{
	protected static $_ERROR_STATE = 0;
	protected static $_VALID_STATE = 1;
	
	private $adapter;
	
	public $id;
	public $serial;
	public $date_add;
	public $state;
	
	public $ip_visited;
	public $date_visited;
	
	protected $product_data;
	protected $customer_data;
	
	public function __construct($id, Adapter\SerialAdapterInterface $adapter)
	{
		$this->adapter = $adapter;
		$this->load($id);
	}
	
	public function loadBySerial($serial)
	{
		if (!empty($serial))
		{
			$data = $this->adapter->loadBySerial($serial);
			$this->init($data);
		}
	}
	
	protected function load($id)
	{
		if ($id > 0)
		{
			$data = $this->adapter->load($id);
			$this->init($data);
		}
	}
	
	public function init($data)
	{
		if (!empty($data))
		{
			foreach ($data as $key => $value)
			{
				if (property_exists($this, $key))
					$this->{$key} = sanitize_text_field($value);
			}
				
			$this->customer_data = array(
				'name' => isset($data['customer'])? $data['customer']: "",
			);
				
			$this->product_data = array(
				'model' => isset($data['product_model'])? $data['product_model']: "",
			);
		}
	}
	
	public function checkData()
	{
		if (!empty($this->serial))
		{
			$data = $this->adapter->loadBySerial($this->serial);
			if (!empty($data))
				return "Serial already exists in the database";
		}
		
		return true;
	}
	
	public function delete()
	{
		if ($this->id > 0)
			return $this->adapter->delete($this->id);
		return false;
	}
	
	public function save()
	{
		$data = array(
			'serial' => $this->serial,
			'customer' => $this->getCustomerData('name', ""),
			'product_model' => $this->getProductData('model', ""),
			'state' => $this->state,
			'ip_visited' => $this->ip_visited,
			'date_visited' => $this->date_visited,
		);
		
		if ($this->id > 0)
			return $this->adapter->update($this->id, $data);
		else
			return $this->adapter->insert($data);
		
		return false;
	}
	
	public function setVisited()
	{
		if ($this->id > 0)
			$this->adapter->update($this->id, array('ip_visited' => Utils::get_client_ip(), 'date_visited' => date('Y-m-d H:i:s')));
	}
	
	public function isLoaded()
	{
		return $this->id !== null;
	}
	
	public function isValid()
	{
		return self::stateIsValid($this->state);
	}
	
	public function isError()
	{
		return self::stateIsError($this->state);
	}
	
	public function getProductData($key, $default = null)
	{
		return isset($this->product_data[$key])? $this->product_data[$key]: $default;
	}
	
	public function getCustomerData($key, $default = null)
	{
		return isset($this->customer_data[$key])? $this->customer_data[$key]: $default;
	}
	
	public function getAdminHTMLForm()
	{
		$fields = array(
			'serial' => array('label' => "Serial", 'value' => !empty($this->serial)? $this->serial: ""),
			'customer' => array('label' => "Customer", 'value' => $this->getCustomerData('name', "")),
			'product_model' => array('label' => "Product Model", 'value' => $this->getProductData('model', "")),
		);
		
		$html = "";
		
		if ($this->id > 0)
			$html .= "<h2>Edit serial</h2>";
		else
			$html .= "<h2>Add serial</h2>";
		
		$html .= '<form method="post">
			<input type="hidden" name="id" value="'.$this->id.'">';
		$html .= wp_nonce_field('serial-form', 'secu');
		
		$html .= '<table class="form-table"><tbody>';
		
		foreach ($fields as $id => $field)
		{
			$html .= '<tr>
				<th><label for="'.$id.'">'.$field['label'].'</label></th>
				<td><input type="text" name="'.$id.'" id="'.$id.'" value="'.$field['value'].'"></td>
			</tr>';
		}
		
		// State
		$html .= '<tr>
			<th><label for="state">State</label></th>
			<td><select id="state" name="state">
				<option value="'.self::$_VALID_STATE.'"'.($this->state == self::$_VALID_STATE? " selected": "").'>Valid</option>
				<option value="'.self::$_ERROR_STATE.'"'.($this->state == self::$_ERROR_STATE? " selected": "").'>Invalid</option>
			</select></td>
		</tr>';
		
		// Submit button
		$html .= '<tr><th><input type="submit" value="Save" class="button" /></th></tr>';
		
		$html .= '</tbody></table></form>';

		return $html;
	}
	
	public static function exists($id)
	{
		$obj = new self($id);
		return $obj->isLoaded();
	}
	
	public static function stateIsValid($state)
	{
		return $state == self::$_VALID_STATE;
	}
	
	public static function stateIsError($state)
	{
		return $state == self::$_ERROR_STATE;
	}
}