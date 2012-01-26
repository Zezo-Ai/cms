<?php

/**
 *
 */
class bUserWidgetSetting extends bBaseSettingsModel
{
	protected $tableName = 'userwidgetsettings';
	protected $model = 'bUserWidget';
	protected $foreignKey = 'widget';

	public function init()
	{
		parent::init();
		$this->indexes[] = array('column' => 'widget_id, key', 'unique' => true);
	}

	/**
	 * Returns an instance of the specified model
	 * @return object The model instance
	 * @static
	 */
	public static function model($class = __CLASS__)
	{
		return parent::model($class);
	}
}
