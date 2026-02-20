<?php
class MemberInvitationFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
	public static $allowed_actions = array (
		'doSendInvitation',
		'ItemEditForm'
	);
	function ItemEditForm()
	{
		$form = parent::ItemEditForm();
		$formActions = $form->Actions();
		$button = FormAction::create('doSendInvitation');
		$button->setTitle('Send Invitation');
		$formActions->push($button);
		$form->setActions($formActions);
		return $form;
	}

	public function doSendInvitation($data, $form) {
		// Populate the record with form data
		$form->saveInto($this->record);
		
		// Validate the record
		$result = $this->record->validate();
		if (!$result->valid()) {
			// Validation failed, return form with error message
			$form->sessionMessage($result->message(), 'bad');
			return $this->edit($this->getRequest());
		}
		else {
			$this->record->DateSent = SS_Datetime::now()->Rfc2822();
			$this->record->write();
			$this->record->sendInvitation();

			// Force a content refresh
			$this->getRequest()->addHeader('X-Pjax', 'Content');
			// redirect back to admin section
			$backLink = $this->getBacklink();
			$controller = $this->getToplevelController();
			return $controller->redirect($backLink, 302);
		}
		

	} 
}