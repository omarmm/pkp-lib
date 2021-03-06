<?php

/**
 * @file controllers/wizard/fileUpload/form/PKPSubmissionFilesUploadForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFilesUploadForm
 * @ingroup controllers_wizard_fileUpload_form
 *
 * @brief Form for adding/editing a submission file
 */


import('controllers.wizard.fileUpload.form.SubmissionFilesUploadBaseForm');

class PKPSubmissionFilesUploadForm extends SubmissionFilesUploadBaseForm {

	/** @var array */
	var $_uploaderRoles;

	/** @var array */
	var $_uploaderGroupIds;


	/**
	 * Constructor.
	 * @param $request Request
	 * @param $submissionId integer
	 * @param $uploaderRoles array
	 * @param $uploaderGroupIds array|null
	 * @param $stageId integer One of the WORKFLOW_STAGE_ID_* constants.
	 * @param $fileStage integer
	 * @param $revisionOnly boolean
	 * @param $stageId integer
	 * @param $reviewRound ReviewRound
	 * @param $revisedFileId integer
	 */
	function PKPSubmissionFilesUploadForm($request, $submissionId, $stageId, $uploaderRoles, $uploaderGroupIds, $fileStage,
			$revisionOnly = false, $reviewRound = null, $revisedFileId = null, $assocType = null, $assocId = null) {

		// Initialize class.
		assert(is_null($uploaderRoles) || (is_array($uploaderRoles) && count($uploaderRoles) >= 1));
		$this->_uploaderRoles = $uploaderRoles;

		assert(is_null($uploaderGroupIds) || (is_array($uploaderGroupIds) && count($uploaderGroupIds) >= 1));
		$this->_uploaderGroupIds = $uploaderGroupIds;

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		parent::SubmissionFilesUploadBaseForm(
			$request, 'controllers/wizard/fileUpload/form/fileUploadForm.tpl',
			$submissionId, $stageId, $fileStage, $revisionOnly, $reviewRound, $revisedFileId, $assocType, $assocId
		);

		// Disable the genre selector for review file attachments
		if ($fileStage == SUBMISSION_FILE_REVIEW_ATTACHMENT) {
			$this->setData('isReviewAttachment', true);
		}
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the uploader roles.
	 * @return array
	 */
	function getUploaderRoles() {
		assert(!is_null($this->_uploaderRoles));
		return $this->_uploaderRoles;
	}

	/**
	 * Get the uploader group IDs.
	 * @return array|null
	 */
	function getUploaderGroupIds() {
		return $this->_uploaderGroupIds;
	}


	//
	// Implement template methods from Form
	//
	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('genreId', 'uploaderUserGroupId'));
		return parent::readInputData();
	}

	/**
	 * @copydoc Form::validate()
	 */
	function validate($request) {
		// Is this a revision?
		$revisedFileId = $this->getRevisedFileId();
		if ($this->getData('revisionOnly')) {
			assert($revisedFileId > 0);
		}

		// Retrieve the request context.
		$router = $request->getRouter();
		$context = $router->getContext($request);
		if (
			$this->getData('fileStage') != SUBMISSION_FILE_REVIEW_ATTACHMENT and
			!$revisedFileId
		) {
			// Add an additional check for the genre to the form.
			$this->addCheck(
				new FormValidatorCustom(
					$this, 'genreId', FORM_VALIDATOR_REQUIRED_VALUE,
					'submission.upload.noGenre',
					create_function(
						'$genreId,$genreDao,$context',
						'return is_a($genreDao->getById($genreId, $context->getId()), "Genre");'
					),
					array(DAORegistry::getDAO('GenreDAO'), $context)
				)
			);
		}

		// Validate the uploader's user group.
		$uploaderUserGroupId = $this->getData('uploaderUserGroupId');
		if ($uploaderUserGroupId) {
			$user = $request->getUser();
			$this->addCheck(
				new FormValidatorCustom(
					$this, 'uploaderUserGroupId', FORM_VALIDATOR_REQUIRED_VALUE,
					'submission.upload.invalidUserGroup',
					create_function(
						'$userGroupId,$userGroupDao,$userId',
						'return $userGroupDao->userInGroup($userId, $userGroupId);'
					),
					array(DAORegistry::getDAO('UserGroupDAO'), $user->getId(), $context)
				)
			);
		}

		return parent::validate();
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		// Retrieve available submission file genres.
		$genreList = $this->_retrieveGenreList($request);
		$this->setData('submissionFileGenres', $genreList);

		// Retrieve the current context.
		$router = $request->getRouter();
		$context = $router->getContext($request);
		assert(is_a($context, 'Context'));

		// Retrieve the user's user groups.
		$user = $request->getUser();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$assignedUserGroups = $userGroupDao->getByUserId($user->getId(), $context->getId());

		// Check which of these groups make sense in the context
		// from which the uploader was instantiated.
		// FIXME: The sub editor role may only be displayed if the
		// user is assigned to the current submission as a sub
		// editor, see #6000.
		$uploaderRoles = $this->getUploaderRoles();
		$uploaderUserGroups = $this->getUploaderGroupIds();
		$uploaderUserGroupOptions = array();
		$highestAuthorityUserGroupId = null;
		$highestAuthorityRoleId = null;
		while($userGroup = $assignedUserGroups->next()) { /* @var $userGroup UserGroup */
			// Exclude groups outside of the uploader roles.
			if (!in_array($userGroup->getRoleId(), $uploaderRoles)) continue;

			// If a specific subset of user groups was specified
			// and the current one is outside the set, exclude it.
			if ($uploaderUserGroups !== null && !in_array($userGroup->getId(), $uploaderUserGroups)) continue;

			$uploaderUserGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();

			// Identify the first of the user groups that belongs
			// to the role with the lowest role id (=highest authority
			// level). We'll need this information to identify the default
			// selection, see below.
			if (is_null($highestAuthorityUserGroupId) || $userGroup->getRoleId() <= $highestAuthorityRoleId) {
				$highestAuthorityRoleId = $userGroup->getRoleId();
				if (is_null($highestAuthorityUserGroupId) || $userGroup->getId() < $highestAuthorityUserGroupId) {
					$highestAuthorityUserGroupId = $userGroup->getId();
				}
			}
		}
		if (empty($uploaderUserGroupOptions)) fatalError('Invalid uploader roles!');
		$this->setData('uploaderUserGroupOptions', $uploaderUserGroupOptions);

		// Identify the default user group (only required when there is
		// more than one group).
		$defaultUserGroupId = null;
		if (count($uploaderUserGroupOptions) > 1) {
			// See whether the current user has been assigned as
			// a workflow stage participant.
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId(
				$this->getData('submissionId'),
				$this->getStageId(),
				null,
				$user->getId()
			);

			while ($stageAssignment = $stageAssignments->next()) { /* @var $stageSignoff Signoff */
				if (isset($uploaderUserGroupOptions[$stageAssignment->getUserGroupId()])) {
					$defaultUserGroupId = $stageAssignment->getUserGroupId();
					break;
				}
			}

			// If we didn't find a corresponding stage signoff then
			// use the user group with the highest authority as default.
			if (is_null($defaultUserGroupId)) $defaultUserGroupId = $highestAuthorityUserGroupId;
		}
		$this->setData('defaultUserGroupId', $defaultUserGroupId);

		// Include a status message for this installation's max file upload size.
		$this->setData('maxFileUploadSize', get_cfg_var('upload_max_filesize'));

		return parent::fetch($request);
	}

	/**
	 * Save the submission file upload form.
	 * @see Form::execute()
	 * @param $request Request
	 * @return SubmissionFile if successful, otherwise null
	 */
	function &execute($request) {
		// Identify the file genre and category.
		$revisedFileId = $this->getRevisedFileId();
		if ($revisedFileId) {
			// The file genre and category will be copied over from the revised file.
			$fileGenre = null;
		} else {
			// This is a new file so we need the file genre and category from the form.
			$fileGenre = $this->getData('genreId') ? (int)$this->getData('genreId') : null;
		}

		// Retrieve the uploader's user group.
		$uploaderUserGroupId = $this->getData('uploaderUserGroupId');
		if (!$uploaderUserGroupId) fatalError('Invalid uploader user group!');

		// Identify the uploading user.
		$user = $request->getUser();
		assert(is_a($user, 'User'));

		$assocType = $this->getData('assocType') ? (int) $this->getData('assocType') : null;
		$assocId = $this->getData('assocId') ? (int) $this->getData('assocId') : null;

		// Upload the file.
		$submissionFile = $this->_uploadFile($request, $user, $uploaderUserGroupId, $revisedFileId, $fileGenre, $assocType, $assocId);

		$fileStage = $this->getData('fileStage');
		if ($submissionFile && ($fileStage == SUBMISSION_FILE_REVIEW_FILE || $fileStage == SUBMISSION_FILE_REVIEW_ATTACHMENT || $fileStage == SUBMISSION_FILE_REVIEW_REVISION)) {
			// Add the uploaded review file to the review round.
			$reviewRound = $this->getReviewRound();
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFileDao->assignRevisionToReviewRound($submissionFile->getFileId(), $submissionFile->getRevision(), $reviewRound);
		}

		if ($submissionFile) {
			$this->_logEvent($request, $user, $submissionFile, $assocType, $revisedFileId, $fileStage);
		}

		return $submissionFile;
	}


	//
	// Private helper methods
	//
	/**
	 * Retrieve the genre list.
	 * @param $request Request
	 * @return array
	 */
	function _retrieveGenreList($request) {
		$context = $request->getContext();
		$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
		$dependentFilesOnly = $request->getUserVar('dependentFilesOnly') ? true : false;
		$genres = $genreDao->getByDependenceAndContextId($dependentFilesOnly, $context->getId());

		// Transform the genres into an array and
		// assign them to the form.
		$genreList = array();
		while ($genre = $genres->next()) {
			$genreList[$genre->getId()] = $genre->getLocalizedName();
		}
		return $genreList;
	}

	/**
	 * Upload the file in an app-specific manner.
	 * must be overridden in subclasses.
	 * @param PKPRequest $request
	 * @param PKPUser $user
	 * @param $uploaderUserGroupId int
	 * @param int  $revisedFileId
	 * @param int $fileGenre
	 * @param int $assocType
	 * @param int $assocType
	 * @return SubmissionFile
	 */
	function _uploadFile($request, $user, $uploaderUserGroupId, $revisedFileId, $fileGenre, $assocType, $assocId) {
		assert(false);
	}

	/**
	 * Log the upload event.
	 * Must be overridden in subclasses.
	 * @param PKPRequest $request
	 * @param PKPUser $user
	 * @param SubmissionFile $submissionFile
	 * @param int $assocType
	 * @param int $revisedFileId
	 * @param int $fileStage
	 */
	function _logEvent($request, $user, $submissionFile, $assocType, $revisedFileId, $fileStage) {
		assert(false);
	}
}

?>
