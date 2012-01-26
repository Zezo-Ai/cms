<?php

/**
 *
 */
class bSite extends bBaseModel
{
	protected $tableName = 'sites';

	protected $attributes = array(
		'handle' => array('type' => bAttributeType::String, 'maxLength' => 150, 'required' => true),
		'label'  => array('type' => bAttributeType::String, 'maxLength' => 500, 'required' => true),
		'url'    => array('type' => bAttributeType::String, 'required' => true)
	);

	protected $hasBlocks = array(
		'blocks' => array('through' => 'bSiteBlock', 'foreignKey' => 'site')
	);

	protected $hasContent = array(
		'content' => array('through' => 'bSiteContent', 'foreignKey' => 'site')
	);

	protected $hasMany = array(
		'settings'     => array('model' => 'bSiteSettings', 'foreignKey' => 'site'),
		'assetFolders' => array('model' => 'bAssetFolder', 'foreignKey' => 'site'),
		'routes'       => array('model' => 'bRoute', 'foreignKey' => 'site'),
		'sections'     => array('model' => 'bSection', 'foreignKey' => 'site')
	);

	protected $indexes = array(
		array('column' => 'handle', 'unique' => true),
	);

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
