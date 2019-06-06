<?php
class MemberInvitationAcceptForm extends Form 
{
	public function __construct($controller, $name) {

		$this->controller = $controller;
			
		$hash = $controller->getRequest()->param('ID');
		$invite = MemberInvitation::get()->filter('TempHash', $hash)->first();

		$firstName = ($invite) ? $invite->FirstName : '';
		$surname = ($invite) ? $invite->Surname : '';
		
		$fields = FieldList::create(
			TextField::create(
				'FirstName',
				_t('MemberInvitation.ACCEPTFORM_FIRSTNAME', 'Name'),
				$firstName
			),
			ConfirmedPasswordField::create('Password'),
			HiddenField::create('HashID')->setValue($hash)
		);
		if($surname) {
			$fields->insertAfter(
				TextField::create(
					'Surname',
					_t('MemberInvitation.ACCEPTFORM_SURNAME', 'Surname'),
					$surname
				), 
				'FirstName'
			);
		}
		else {
			HiddenField::create('Surname');
		};
		$actions = FieldList::create(
			FormAction::create('saveInvite', _t('MemberInvitation.ACCEPTFORM_REGISTER', 'Register'))
		);
		$requiredFields = RequiredFields::create('FirstName');
		$form = new FoundationForm($this, 'MemberInvitationAcceptForm', $fields, $actions, $requiredFields);
		
		Session::set('MemberInvitation.accepted', true);
		$this->extend('updateForm', $form);	
		
		parent::__construct($controller, $name, $fields, $actions);
	}
}
