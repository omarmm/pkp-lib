<?php

/**
 * @file controllers/tab/settings/contextIndexing/form/ContextIndexingForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextIndexingForm
 * @ingroup controllers_tab_settings_indexing_form
 *
 * @brief Form to edit indexing settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class ContextIndexingForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function ContextIndexingForm($wizardMode = false) {
		$settings = array(
			'searchDescription' => 'string',
			'searchKeywords' => 'string',
			'customHeaders' => 'string'
		);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/contextIndexing/form/contextIndexingForm.tpl', $wizardMode);
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * Get all locale field names
	 */
	function getLocaleFieldNames() {
		return array('searchDescription', 'searchKeywords', 'customHeaders');
	}
}

?>
