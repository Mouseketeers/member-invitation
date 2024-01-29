<?php

namespace Mouseketeers\SilverstripeMemberInvitation;

use SilverStripe\Forms\Form;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\ListboxField;

class MemberInvitationForm extends Form 
{
	public function __construct($controller, $name) {

		$allowed_groups = [];

		$frontend_groups = MemberInvitation::config()->get('frontend_groups');

		if(!$frontend_groups) {
			$allowed_groups = Group::get();
		}
		else {
			
			$allowed_group_codes = [];
			$member = Security::getCurrentUser();
			
			foreach ($frontend_groups as $key => $value) {
				if(is_array($value)) {
					if($member->inGroup($key)) {
						$allowed_group_codes = array_merge($allowed_group_codes, $value);	
					}
				}
				else {
					$allowed_group_codes[] = $value;
				}
			}
			if($allowed_group_codes) {
				$allowed_groups = Group::get()->filter('Code', $allowed_group_codes)->sort('Title');
			}	
		}
        		
		if($allowed_groups) {
			$fields = FieldList::create(
	            TextField::create(
	            	'FirstName',
	            	_t('MemmberInvitation.INVITE_FIRSTNAME', 'First name')
	            ),
	            TextField::create(
	            	'Surname',
	            	_t('MemmberInvitation.INVITE_SURNAME', 'Surname')
	            ),
	            EmailField::create(
	            	'Email',
	            	_t('MemmberInvitation.INVITE_EMAIL', 'Email')),
				OptionsetField::create(
					'Groups',
					_t('MemmberInvitation.INVITE_GROUP', 'Add to group'),
					$allowed_groups->map('Code', 'Title')->toArray(), 
					$allowed_groups->first()->Code
				)
			);
	        $actions = FieldList::create(
	            FormAction::create(
	            	'sendInvite',
	            	_t('MemmberInvitation.SEND_INVITATION', 'Send Invitation')
				)
	        );
			$requiredFields = RequiredFields::create(array('FirstName', 'Email', 'Groups'));			
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

		$this->extend('updateMemberInvitationForm', $fields, $actions, $requiredFields);
		
		parent::__construct($controller, $name, $fields, $actions, $requiredFields);
	}
}