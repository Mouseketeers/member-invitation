<?php
class MemberInvitationAcceptForm extends Form 
{
	public function __construct($controller, $name) {

		$hash = $controller->getRequest()->param('ID');
		$invite = MemberInvitation::get()->filter('TempHash', $hash)->first();

		$firstName = ($invite) ? $invite->FirstName : '';
		$surname = ($invite) ? $invite->Surname : '';
		$email = ($invite) ? $invite->Email : '';
		
		$fields = FieldList::create(
			HiddenField::create('Email', 'Email', $email),
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
		};
		$actions = FieldList::create(
			FormAction::create('acceptInvite', _t('MemberInvitation.ACCEPTFORM_REGISTER', 'Register'))
		);
		
		$required = new RequiredFields('FirstName');
		
		Session::set('MemberInvitation.accepted', true);
		
		
		$controller->extend('updateAcceptForm', $this, $fields, $actions, $required);
		
		parent::__construct($controller, $name, $fields, $actions, $required);
	}
}
