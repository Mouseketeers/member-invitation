<?php

namespace Mouseketeers\SilverstripeMemberInvitation;

use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\FieldType\DBDatetime;

class MemberInvitationFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
	private static $allowed_actions = [
		'doSendInvitation',
		'ItemEditForm'
	];
	function ItemEditForm()
	{
		$form = parent::ItemEditForm();
		$formActions = $form->Actions();
		$button = FormAction::create('doSendInvitation');
		$button->setTitle('Save and Send Invitation')
			->setUseButtonTag(true)
			->addExtraClass('btn-outline-primary font-icon-tick');
		$formActions->insertAfter('action_doSave', $button);
		$form->setActions($formActions);
		return $form;
	}

	public function doSendInvitation($data, $form) {

		// first save any changes
		$this->record->DateSent = DBDatetime::now()->Rfc2822();
		$this->doSave($data, $form);

        // send invitation
        $invite = MemberInvitation::create();
        $form->saveInto($invite);
        $invite->sendInvitation();
        
        $form->sessionMessage('Invitation has been sent', 'good');

        return $this->redirectAfterSave(false);

	} 
}