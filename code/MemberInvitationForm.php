<?php
class MemberInvitationForm extends Form 
{
	public function __construct($controller, $name) {

		$groups = [];
		$group_codes = [];


		$member = Member::currentUser();
		
		$groups_config = MemberInvitation::config()->get('frontend_groups');
        
		if($groups_config) {
			foreach ($groups_config as $config_group_code => $config_group_codes) {
				if($member->inGroup($config_group_code)) {
					$group_codes = array_merge($group_codes, $config_group_codes);
				}
			}
			if($group_codes) {
				$groups = Group::get()->filter('Code', $group_codes)->sort('Title');
			}
		}
		
		if($groups->exists()) {
			$fields = FieldList::create(
	            TextField::create('FirstName', _t('MemmberInvitation.INVITE_FIRSTNAME', 'First name')),
	            TextField::create('Surname', _t('MemmberInvitation.INVITE_SURNAME', 'Surname')),
	            EmailField::create('Email', _t('MemmberInvitation.INVITE_EMAIL', 'Email')),
	            TextareaField::create('Message', _t('MemmberInvitation.INVITE_MESSAGE', 'Message'), 'You have been invited to join '.SiteConfig::current_site_config()->Title . '.'),
				OptionsetField::create(
					'Groups',
					_t('MemmberInvitation.INVITE_GROUP', 'Add to group'),
					$groups->map('Code', 'Title')->toArray(), 
					$groups->first()->Code
				)
			);
	        $actions = FieldList::create(
	            FormAction::create('sendInvite', _t('MemmberInvitation.SEND_INVITATION', 'Send Invitation'))
	        );
			$required = RequiredFields::create(array('FirstName', 'Email', 'Groups'));			
	    }
        else {
        	$actions = new FieldList();
        	$fields = new FieldList();
			$this->setMessage(
                _t(
                    'MemberInvitation.PERMISSION_FAILURE',
                    "You don't have permission to send user invitations"
                ),
                'warning'
            );
        }

		$this->extend('updateMemberInvitationForm', $fields, $actions, $required);
		
		parent::__construct($controller, $name, $fields, $actions, $required);
	}
}