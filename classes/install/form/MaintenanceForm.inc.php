<?php

/**
 * @file classes/install/form/MaintenanceForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MaintenanceForm
 * @ingroup install_form
 *
 * @brief Base form for system maintenance (install/upgrade).
 */

import('lib.pkp.classes.form.Form');
import('lib.pkp.classes.site.VersionCheck');

class MaintenanceForm extends Form {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor.
	 */
	function MaintenanceForm($request, $template) {
		parent::Form($template);
		$this->_request = $request;
		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr = TemplateManager::getManager($this->_request);
		$templateMgr->assign('version', VersionCheck::getCurrentCodeVersion());

		parent::display($this->_request);
	}

	/**
	 * Fail with a generic installation error.
	 * @param $errorMsg string
	 */
	function installError($errorMsg) {
		$templateMgr = TemplateManager::getManager($this->_request);
		$templateMgr->assign(array('isInstallError' => true, 'errorMsg' => $errorMsg));
		$this->display($this->_request);
	}

	/**
	 * Fail with a database installation error.
	 * @param $errorMsg string
	 */
	function dbInstallError($errorMsg) {
		$templateMgr = TemplateManager::getManager($this->_request);
		$templateMgr->assign(array('isInstallError' => true, 'dbErrorMsg' => empty($errorMsg) ? __('common.error.databaseErrorUnknown') : $errorMsg));
		error_log($errorMsg);
		$this->display($this->_request);
	}

}

?>
