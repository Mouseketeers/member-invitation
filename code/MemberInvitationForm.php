<?php
class MemberInvitationForm extends Form 
{
	public function __construct($controller, $name) {

		$groups = [];
		$group_codes = [];
		
		$member_groups = Member::currentUser()->Groups();
		$groups_config = MemberInvitation::config()->get('frontend_groups');
		
		if($groups_config) {
			foreach ($groups_config as $config_group_code => $config_group_codes) {
				if($config_group_codes) {
					foreach($member_groups as $member_group) {
						if($member_group->Code == $config_group_code) {
							$group_codes = array_merge($group_codes, $config_group_codes);
						}
					}
				}
			}
			if($group_codes) {
				$groups = Group::get()->filter('Code', $group_codes);	
			}
		}
		if(!$groups) {
			$groups = $member_groups;
		}
		$fields = FieldList::create(
            TextField::create('FirstName', _t('MemmberInvitation.INVITE_FIRSTNAME', 'First name')),
            TextField::create('Surname', _t('MemmberInvitation.INVITE_SURNAME', 'Surname')),
            EmailField::create('Email', _t('MemmberInvitation.INVITE_EMAIL', 'Email')),
            OptionsetField::create('Groups', _t('MemmberInvitation.INVITE_GROUP', 'Add to group'), $groups->map('Code', 'Title')->toArray())
        );
        $actions = FieldList::create(
            FormAction::create('sendInvite', _t('MemmberInvitation.SEND_INVITATION', 'Send Invitation'))
        );
		$required = RequiredFields::create(array('FirstName', 'Email', 'Groups'));

		$this->extend('updateMemberInvitationForm', $fields, $actions, $required);
		
		parent::__construct($controller, $name, $fields, $actions, $required);
	}
}
