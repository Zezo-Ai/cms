<?php
namespace Blocks;

/**
 * Handles content management tasks
 */
class ContentController extends BaseController
{
	/**
	 * All content actions require the user to be logged in
	 */
	public function init()
	{
		$this->requireLogin();
	}

	/**
	 * Saves a section
	 */
	public function actionSaveSection()
	{
		$this->requirePostRequest();

		$sectionSettings['name'] = b()->request->getPost('name');
		$sectionSettings['handle'] = b()->request->getPost('handle');

		$maxEntries = b()->request->getPost('max_entries');
		$sectionSettings['max_entries'] = ($maxEntries ? $maxEntries : null);

		$sectionSettings['sortable'] = (b()->request->getPost('sortable') === 'y');

		$urlFormat = b()->request->getPost('url_format');
		$sectionSettings['url_format'] = ($urlFormat ? $urlFormat : null);

		$template = b()->request->getPost('template');
		$sectionSettings['template'] = ($template ? $template : null);

		$sectionBlocksData = b()->request->getPost('blocks');
		$sectionId = b()->request->getPost('section_id');

		$section = b()->content->saveSection($sectionSettings, $sectionBlocksData, $sectionId);

		// Did it save?
		if (!$section->errors)
		{
			b()->user->setMessage(MessageType::Notice, 'Section saved.');

			$url = b()->request->getPost('redirect');
			if ($url !== null)
				$this->redirect($url);
		}
		else
		{
			b()->user->setMessage(MessageType::Error, 'Couldn’t save section.');
		}

		// Get Block instances for each selected block
		$sectionBlocks = array();

		if (!empty($sectionBlocksData['selections']))
		{
			foreach ($sectionBlocksData['selections'] as $blockId)
			{
				$block = b()->blocks->getBlockById($blockId);
				$block->required = (isset($sectionBlocksData['required'][$blockId]) && $sectionBlocksData['required'][$blockId] === 'y');
				$sectionBlocks[] = $block;
			}
		}
		

		// Reload the original template
		$this->loadRequestedTemplate(array(
			'section'       => $section,
			'sectionBlocks' => $sectionBlocks
		));
	}

	/**
	 * Creates a new entry and redirects to its edit page
	 */
	public function actionCreateEntry()
	{
		$sectionId = b()->request->getParam('sectionId');
		$authorId = b()->user->id;
		$entry = b()->content->createEntry($sectionId, $authorId);
		$this->redirect('content/edit/'.$entry->id);
	}
}
