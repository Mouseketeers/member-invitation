<?php
class MemberInvitationForm extends Form 
{
	public function __construct($controller, $name) {

		$groups = [];
		$group_codes = [];
		
		$member_groups = Member::currentUser()->Groups()->sort('Title');
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
				$groups = Group::get()->filter('Code', $group_codes)->sort('Title');
				// if on a subsite only show groups that have access to the current site
				// if(class_exists('Subsite') && $current_subsite_id = Subsite::currentSubsiteID()) {
				// 	$groups->filter('SubsiteID', $current_subsite_id)->leftJoin("Group_Subsites", "\"Group_Subsites\".\"GroupID\" = \"Group\".\"ID\"");
				// }
			}
		}
		// if(!$groups->exists()) {
		// 	$groups = $member_groups;
		// }


		$group_count = $groups->count();

		if($group_count) {
			$fields = FieldList::create(
	            TextField::create('FirstName', _t('MemmberInvitation.INVITE_FIRSTNAME', 'First name'))->setDisabled(true),
	            TextField::create('Surname', _t('MemmberInvitation.INVITE_SURNAME', 'Surname')),
	            EmailField::create('Email', _t('MemmberInvitation.INVITE_EMAIL', 'Email'))
	            
	        );

	        if($group_count > 0) {
	        	$fields->add(
	        		OptionsetField::create(
	        			'Groups',
	        			_t('MemmberInvitation.INVITE_GROUP', 'Add to group'),
	        			$groups->map('Code', 'Title')->toArray(), 
	        			$groups->first()->Code
	        		)
	        	);	
	        }
	        else {
	        	$fields->add(
	        		LiteralField::create('Groups', 'You are not allowed to add members to any groups')
	        	);
	        }
	        
	        $actions = FieldList::create(
	            FormAction::create('sendInvite', _t('MemmberInvitation.SEND_INVITATION', 'Send Invitation'))->setDisabled(true)
	        );
			$required = RequiredFields::create(array('FirstName', 'Email', 'Groups'));
		}
		else {
			$fields = FieldList::create(
				LiteralField::create('Groups', 'You are not allowed to add members to any groups')
			);
			$actions = FieldList::create();
		}




		$this->extend('updateMemberInvitationForm', $fields, $actions, $required);
		
		parent::__construct($controller, $name, $fields, $actions, $required);
	}
}
