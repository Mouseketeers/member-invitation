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

		$now = SS_Datetime::now()->Rfc2822();
		$this->record->DateSent = $now;
		$this->doSave($data, $form);

        $invite = MemberInvitation::create();
        $invite->DateSent = SS_Datetime::now()->Rfc2822();
        $form->saveInto($invite);

        Config::inst()->update('SSViewer', 'theme_enabled', true);

        $invite->sendInvitation();

        Config::inst()->update('SSViewer', 'theme_enabled', false);
		
		$controller = $this->getToplevelController();
		 // Force a content refresh
		$controller->getRequest()->addHeader('X-Pjax', 'Content');
		//redirect back to admin section
		$backLink = $this->getBacklink();
		return $controller->redirect($backLink, 302); 
	} 
}