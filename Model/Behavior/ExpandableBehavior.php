<?php
/**
 * ExpandableBehavior will allow you to extend any model with any set of fields
 *
 * It uses a second table/model as a the key/value table, which links back to
 * the primary table/model.  Thus you can store any details you want separate
 * from the main table/model, keeping schema simpler and reducing table size.
 *
 * Usage:
 *   You must make a new table and optionally a Model for that table
 *     It should be named <my_model>_expands (or really anything you like)
 *     It needs to have a primary ID,
 *       a foreignKey linking back to the primary table,
 *       and it must have a "key" field and a "value" field
 *
 *   Then just link in the Behavior and all your saves and finds will
 *     auto-extend with the extra fields/values
 *
 * In your schema:
 *
	public $user_expands = array(
		'id' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 36, 'key' => 'primary', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'user_id' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 36, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'key' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 128, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'value' => array('type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'search' => array('column' => array('user_id', 'key'), 'unique' => 1)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);
 *	);
 *
 *
 * On MyModel:
 *
class User extends AppModel {
	public $name = 'User';
	public $actsAs = array('Expandable.Expandable' => array('with' => 'UserExpand'), 'Containable');
	public $hasMany = array('Expandable.UserExpand');
}
 *
 *
 * For more information on this functionality, and a plain example of
 * functionality, check out the packaged unit tests
 *
 * Primary source:
 * @link http://debuggable.com/posts/unlimited-model-fields-expandable-behavior:48428c2e-9a88-47ec-ae8e-77a64834cda3
 * @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.php
 * @link https://github.com/felixge/debuggable-scraps/blob/master/cakephp/behaviors/expandable/expandable.test.php
 *
 * Repackaged:
 * @link https://github.com/LubosRemplik/CakePHP-Expandable-Plugin
 *
 * Updated:
 * @link https://github.com/zeroasterisk/CakePHP-Expandable-Plugin
 *
 */
class ExpandableBehavior extends ModelBehavior {

	public $defaults = array(
		// if a value is an array or object we can encode/decode via: json
		'encode_json' => true,
		// Ignore all of these fields (never save them) security like whoa!
		'restricted_keys' => array(),
		// CSV strings are awesome -- they let us look FIND_IN_SET() in mysql
		//   if you don't need that, no need for CSV, Expandable will auto-encode/decode JSON
		//   NOTE: don't send indexed arrays, as the keys will be lost
		'encode_csv' => array(),
		// Date inputs from CakePHP can come in as arrays, this is the handler:
		//   'birthdate' => 'Y-m-d',
		//   'card_expires' => 'm/y',
		'encode_date' => array(),
   	);

	public $settings = array();
	private $_eavData = array();

	/**
	 * Setup the model
	 *
	 * @param object Model $Model
	 * @param array $settings
	 * @return boolean
	 */
	public function setup(Model $Model, $settings = array()) {
		if (isset($settings['with'])) {
			$base = array('schema' => $Model->schema());
			$settings = array_merge($settings, $base);
			$settings = array_merge($this->defaults, $settings);
			$settings = Hash::normalize($settings);
			return $this->settings[$Model->alias] = $settings;
		}
	}

	/**
	 * Standard afterFind() callback
	 * Inject the expandable data (as fields)
	 *
	 * @param object Model $Model
	 * @param mixed $results
	 * @param boolean $primary
	 * @return mixed $results
	 */
	public function afterFind(Model $Model, $results, $primary = false) {
		$settings = $this->settings[$Model->alias] ?? [];
		if (!empty($settings['with'])) {
			$with = $settings['with'];
			if (!Hash::check($results, '{n}.' . $with)) {
				return;
			}
			foreach (array_keys($results) as $i) {
				foreach (array_keys($results[$i][$with]) as $j) {
					$key = $results[$i][$with][$j]['key'];
					$value = $results[$i][$with][$j]['value'];
					$results[$i][$Model->alias][$key] = $this->decode($Model, $value);
				}
			}
		}
	}

	/**
	 * Shared method to prepare EAV (Entity-Attribute-Value) data
	 * @access protected
	 * @param Model $Model
	 * @return array
	 */
	protected function _prepareEavData(Model $Model) : array
	{
		$settings = $this->settings[$Model->alias] ?? [];
		if (!isset($settings['schema'])) {
			return [];
		}

		$fieldsToSave = array_diff_key($Model->data[$Model->alias], $settings['schema']);
		if (empty($fieldsToSave)) {
			return [];
		}

		// Get the configured restricted keys and ignore all associated models
		$restricted_keys = array_merge(
			$settings['restricted_keys'],
			array_keys($Model->belongsTo),
			array_keys($Model->hasOne),
			array_keys($Model->hasMany),
			array_keys($Model->hasAndBelongsToMany)
		);

		$eavData = [];
		foreach ($fieldsToSave as $key => $val) {
			// Skip restricted keys
			if (in_array($key, $restricted_keys, true)) {
				continue;
			}

			$eavData[] = [
				'key' => $key,
				'value' => $this->encode($Model, $val, $key)
			];
		}

		return $eavData;
	}

	/**
	 * Validate EAV (Entity-Attribute-Value) data
	 * @access public
	 * @param Model $Model
	 * @param array $options
	 * @return boolean
	 */
	public function beforeValidate(Model $Model, $options = []) : bool
	{
		$this->_eavData ??= $this->_prepareEavData($Model);
		if (empty($this->_eavData)) {
			return true;
		}

		// Validate the prepared EAV data
		$with = $this->settings[$Model->alias]['with'];
		$Model->{$with}->set($this->_eavData);
		if (!$Model->{$with}->validates()) {
			foreach ($Model->{$with}->validationErrors as $field => $errors) {
				$Model->validationErrors[$with . '.' . $field] = $errors;
			}
		}
		return true;
	}

	/**
	 * Save EAV (Entity-Attribute-Value) data
	 * @access public
	 * @param Model $Model
	 * @param boolean $created
	 * @param array $options
	 * @return boolean
	 */
	public function afterSave(Model $Model, $created, $options = [])
	{
		// If validation was skipped, prepare data now
		if (empty($this->_eavData)) {
			$this->_eavData = $this->_prepareEavData($Model);
		}

		$settings = $this->settings[$Model->alias] ?? [];
		if (!empty($settings['with']) && !empty($this->_eavData)) {
			$with = $settings['with'];
			$assoc = $Model->hasMany[$with];
			$foreignKey = $assoc['foreignKey'];
			$id = $Model->id;

			foreach ($this->_eavData as $data) {
				$fieldId = $Model->{$with}->field('id', array(
					$with . '.' . $foreignKey => $id,
					$with . '.key' => $data['key']
				));

				if (!empty($fieldId)) {
					$Model->{$with}->id = $fieldId;
				} else {
					$Model->{$with}->create();
				}

				// The data is already structured correctly with key and value
				$data[$foreignKey] = $id;
				$saved = $Model->{$with}->save($data);
			}
			return true;
		}
	}

	/**
	 * Optionally encode various inputs into a normalized storage string
	 *   see $defaults to see what settings are possible
	 *
	 * @param mixed $value
	 * @return string $value
	 */
	private function encode(Model $Model, $value, $key) {
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!empty($settings['encode_date'])) {
			$value = $this->encode_date($Model, $value, $key);
		}
		if (!empty($settings['encode_csv'])) {
			$value = $this->encode_csv($Model, $value, $key);
		}
		if (!empty($settings['encode_json'])) {
			$value = $this->encode_json($Model, $value, $key);
		}
		return $value;
	}

	/**
	 * Encode dates which may be passed in as an array
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_date(Model $Model, $value, $key) {
		if (!is_array($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (empty($settings['encode_date'][$key])) {
			return $value;
		}
		$format = $settings['encode_date'][$key];
		if (!is_string($format)) {
			$format = 'Y-m-d';
		}
		// parses inputs generated by CakePHP date helpers
		$dateField = Hash::filter(array_merge(array('year' => date('Y'), 'month' => date('m'), 'day' => date('d')), $value));
		$datestring = $dateField['year'] . '-' .$dateField['month'] . '-' . $dateField['day'];
		$dateObject = DateTime::createFromFormat('Y-m-d', $datestring);
		return $dateObject->format($format);
	}

	/**
	 * Encode fields which may be passed in as an array, as a CSV string
	 * (for use with FIND_IN_SET() searching in MySQL)
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_csv(Model $Model, $value, $key) {
		if (!is_array($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (empty($settings['encode_csv'][$key]) && !in_array($key, $settings['encode_csv'], true)) {
			return $value;
		}
		array_walk($value, create_function('&$val', '$val = trim(strval($val));'));
		return implode(',', $value);
	}

	/**
	 * Optionally encode non-string/numeric inputs into JSON strings
	 *
	 * @param Model $Model
	 * @param mixed $value
	 * @param string $key
	 * @return string $value
	 */
	private function encode_json(Model $Model, $value, $key) {
		if ($value === true) {
			return 'true';
		}
		if ($value === false) {
			return 'false';
		}
		if ($value === null) {
			return 'null';
		}
		if (is_string($value) || is_numeric($value)) {
			return $value;
		}
		return json_encode($value);
	}

	/**
	 * Optionally decode JSON strings into true/expanded values
	 *
	 * @param string $value
	 * @return mixed $value
	 */
	private function decode(Model $Model, $value) {
		if (empty($value)) {
			return $value;
		}
		$settings = (!empty($this->settings[$Model->alias]) ? $this->settings[$Model->alias] : array());
		if (!$settings['encode_json']) {
			return $value;
		}
		if ($value == 'true') {
			return true;
		}
		if ($value == 'false') {
			return false;
		}
		if ($value == 'null') {
			return null;
		}
		if (is_string($value) && (substr($value, 0, 1) == '{'  || substr($value, 0, 1) == '[')) {
			$decoded = @json_decode($value, true);
			if ($decoded != null) {
				return $decoded;
			}
		}
		return $value;
	}
}
