<?php

class MemberInvitation extends DataObject 
{

	public static $allowed_actions = array (
		'ItemEditForm'
	);

	private static $db = array(
		'FirstName' => 'Varchar',
		'Surname' => 'Varchar',
		'Email' => 'Varchar(254)',
		'FromEmail' => 'Varchar(254)',
		'EmailSubject' => 'Varchar(254)',
		'Message' => 'HTMLText',
		'TempHash' => 'Varchar',
		'Groups' => 'Text',
		'SubsiteID' => 'Int',
		'DateSent' => 'SS_Datetime',
		'Accepted' => 'Boolean'
	);

	private static $indexes = array(
		'Email' => true,
		'TempHash' => true
	);

	private static $summary_fields = array(
		'FirstName',
		'Surname',
		'Email',
		'Status',
		'DateSent'
	);

	public function populateDefaults() {
		parent::populateDefaults();

		$defaultFromEmail = self::config()->get('default_from_email'); 
		$this->FromEmail = ($defaultFromEmail) ? $defaultFromEmail :  Member::currentUser()->Email;


		$defaultEmailSubject = self::config()->get('default_email_subject');
		$this->EmailSubject = ($defaultEmailSubject) ? $defaultEmailSubject : 'Invitation to join '.SiteConfig::current_site_config()->Title;

		$defaultMessage = self::config()->get('default_message');
		$this->Message = ($defaultMessage) ? $defaultMessage : '<p>You have been invited to join '.SiteConfig::current_site_config()->Title.'.</p>';
		
		if(class_exists('Subsite')) {
			$this->SubsiteID = Subsite::currentSubsiteID();
		}
		if($defaultGroups = self::config()->get('default_groups')) {
			$this->Groups = $defaultGroups;
		}

	}

	public function getCMSValidator()
	{
		return new RequiredFields(
			'Email',
			'Groups',
			'FromEmail',
			'EmailSubject',
			'SubsiteID'
		);
	}

	public function getEditLink()
	{
		$admin = SecurityAdmin::singleton();
		$fields = $admin->getEditForm()->Fields();
		$grid = $fields->dataFieldByName('MemberInvitations');
		return Controller::join_links(
			$grid->Link("item"),
			$this->ID,
			"edit"
		);
	}

	public function getTitle() 
	{
		return $this->Email;
	}

	public function getStatus()
	{
		if($this->Accepted) {
			return 'Accepted';
		}
		if($this->getIsExpired()) {
			return 'Expired';
		}
		if($this->DateSent) {
			return 'Sent';
		}
		return 'Not Sent';
	}

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		if(class_exists('Subsite')) {
			$fields->replaceField(
				'SubsiteID',
				DropdownField::create(
					'SubsiteID', 
					'Site', 
					Subsite::all_sites()->map('ID', 'Title')
				)
			);
		}
		else {
			$fields->removeByName('SubsiteID');
		}

		$groups = Group::get();
		$groupsMap = array();
		foreach ($groups as $group) {
			$groupsMap[$group->Code] = $group->getBreadcrumbs(' > ');
		}
		asort($groupsMap);
		$fields->replaceField(
			'Groups',
			ListboxField::create('Groups', singleton('Group')->i18n_plural_name())
				->setMultiple(true)
				->setSource($groupsMap)
				->setAttribute(
					'data-placeholder',
					_t('Member.ADDGROUP', 'Select group', 'Placeholder text for a dropdown')
				)
				->setTitle('Add to groups')
		);	

		if(!$this->DateSent) {
			$fields->removeByName('DateSent');
		}
		else {
			$fields->dataFieldByName('DateSent')->setDisabled(true);	
		}
		$fields->removeByName('TempHash');
		$fields->removeByName('Accepted');
		return $fields;
	}

	public function onBeforeWrite()
	{
		if (!$this->ID) {
			$generator = new RandomGenerator();
			$this->TempHash = $generator->randomToken('sha1');
		}
		parent::onBeforeWrite();
	}
	public function validate() 
	{
		$valid = parent::validate();

		// if (self::get()->filter('Email', $this->Email)->first()) {
		// 	$valid->error(
		// 		_t('MemberInvitation.INVITE_EXISTS', 'An invitation with this e-mail already exists.')
		// 	);
		// }

		if (Member::get()->filter('Email', $this->Email)->first()) {
			// Member already exists
			$valid->error(
				_t('MemberInvitation.MEMBER_ALREADY_EXISTS', 'An member with this e-mail is already registered.')
			);
		}
		return $valid;
	}

	public function getIsExpired()
	{
		return false;
		$result = false;
		$days = self::config()->get('days_to_expiry');
		$time = SS_Datetime::now()->Format('U');
		$ago = abs($time - strtotime($this->Created));
		$rounded = round($ago / 86400);
		if ($rounded > $days) {
			$result = true;
		}
		return $result;
	}

	public function canCreate($member = null)
	{
		return Permission::check('ACCESS_USER_INVITATIONS');
	}
}