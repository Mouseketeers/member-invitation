<?php
class MemberInvitationForm extends Form 
{
	public function __construct($controller, $name) {

		$groups = array();

		$member_groups = Member::currentUser()->Groups();
		
		$groupsConfig = MemberInvitation::config()->get('frontend_groups');
		
		if($groupsConfig) {
			foreach ($groupsConfig as $config_group_code => $config_allowed_groups) {
				foreach($member_groups as $member_group) {
					if($member_group->Code == $config_group_code) {
						foreach($config_allowed_groups as $config_allowed_group) {
							$group = Group::get()->filter('Code', $config_allowed_group)->first();
							if($group) {
								$groups[$group->Code] = $group->Title;
							}
						}
					}
				}
			}
		}
		if(!$groups) {
			$groups = $member_groups->map('Code', 'Title')->toArray();
		}
		$fields = FieldList::create(
            TextField::create('FirstName', _t('MemmberInvitation.INVITE_FIRSTNAME', 'First name')),
            TextField::create('Surname', _t('MemmberInvitation.INVITE_SURNAME', 'Surname')),
            EmailField::create('Email', _t('MemmberInvitation.INVITE_EMAIL', 'Email')),
            OptionsetField::create('Groups', _t('MemmberInvitation.INVITE_GROUP', 'Add to group'), $groups)
        );
        $actions = FieldList::create(
            FormAction::create('sendInvite', _t('MemmberInvitation.SEND_INVITATION', 'Send Invitation'))
        );
		$required = RequiredFields::create(array('FirstName', 'Email', 'Groups'));

		$this->extend('updateMemberInvitationForm', $fields, $actions, $required);
		
		parent::__construct($controller, $name, $fields, $actions, $required);
	}
}
