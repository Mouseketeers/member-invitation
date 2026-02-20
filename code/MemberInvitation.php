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
        'Groups' => 'Text',
        'TempHash' => 'Varchar',
        'DateSent' => 'SS_Datetime',
        'Accepted' => 'Boolean'
    );

    private static $has_one = array(
        'InvitedBy' => 'Member',
        'Subsite' => 'Subsite'
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

    private static $default_sort = 'Created DESC';

    public function populateDefaults()
    {

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

        parent::populateDefaults();

    }   
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        if (Permission::checkMember($member, "ADMIN") || Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin")) {
            return true;
        }
        return false;
    }
    public function canView($member = null)
    {
        return $this->canEdit($member);
    } 
    public function canDelete($member = null)
    {
        return $this->canEdit($member);
    }  
    public function setEmailSubject($emailSubject)
    {
        if($emailSubject) 
        {
            $this->setField('EmailSubject', $emailSubject); 
        }
        return $this;
    }
    public function setMessage($message)
    {
        if($message)
        {
            $this->setField('Message', $message);   
        }
        return $this;
    }
    public function getCMSValidator()
    {
        $requiredFields = RequiredFields::create(
            array(
                'Email',
                'FromEmail',
                'EmailSubject',
                'Groups'
            )
        );
        if(class_exists('Subsite')) {
            $requiredFields->addRequiredField('SubsiteID');
        }
        return $requiredFields;
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

        // todo: avoid duplicating this logic
        if(!$this->InvitedByID) {
            $this->InvitedByID = Member::currentUserID();
        }
        
        if(!$this->TempHash) {
            $this->TempHash = $this->generateTempHash();
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
                    _t('MemberInvitation.ADDGROUP', 'Select group', 'Placeholder text for a dropdown')
                )
                ->setTitle('Add to groups')
        );
        if(class_exists('Subsite')) {
            $subsites = Subsite::all_sites();
            $fields->insertAfter(
                DropdownField::create(
                    'SubsiteID', 
                    'Site', 
                    $subsites->map('ID', 'Title')
                ),
                'Groups'
            );
        }
        else {
            $fields->removeByName('SubsiteID');
        }
        if($this->TempHash) {
            if($this->SubsiteID) {
                $siteURL = $this->subsite()->getPrimarySubsiteDomain()->absoluteBaseURL();
            }
            else {
               $siteURL = Director::absoluteBaseURL(); 
            }
            $fields->insertBefore(
               ReadonlyField::create('AcceptLink', 'Accept Link', $siteURL.'invite/accept/'.$this->TempHash)->setRightTitle('Link sent in invitation. You can also copy this and send it in an email of your own (e.g. if a person complains that they haven\'t received the invitation).')
               , 'DateSent'
            );            
        }
        $fields->insertBefore(
           ReadonlyField::create('InvitedByReadOnlyField', 'Invited By', $this->InvitedBy()->getTitle()), 'DateSent'
        );        
        $fields->dataFieldByName('DateSent')->setReadonly(true);
        $fields->replaceField('TempHash', HiddenField::create('TempHash', 'TempHash'));
        $fields->replaceField('Accepted', HiddenField::create('Accepted', 'Accepted'));
        $fields->replaceField('InvitedByID', HiddenField::create('InvitedByID', 'InvitedByID'));

        return $fields;
    }

    public function validate() 
    {
        $valid = parent::validate();

        if(!$this->ID) {
            if (Member::get()->filter('Email', $this->Email)->first()) {
                $valid->error(
                    _t('MemberInvitation.MEMBER_ALREADY_EXISTS', 'An member with this e-mail is already registered.')
                );
                return $valid;
            }
            if (self::get()->filter('Email', $this->Email)->first()) {
                $valid->error(
                    _t('MemberInvitation.INVITE_EXISTS', 'An invitation with this e-mail already exists.')
                );
            }
        }
        return $valid;
    }
    public function sendInvitation()
    {
        // Enable theme for email templates
        Config::inst()->update('SSViewer', 'theme_enabled', true);
        
        if($subsiteID = $this->SubsiteID) {
            $subsite = Subsite::get()->byID($subsiteID);
            $siteURL = 'http://'.$subsite->getPrimarySubsiteDomain()->Domain.'/';
            if($theme = $subsite->Theme) {
                SSViewer::set_theme($theme);
            }
        }
        else {
            $siteURL = Director::absoluteBaseURL();
        }
        
        $result = Email::create()
            ->setFrom($this->FromEmail)
            ->setTo($this->Email)
            ->setSubject($this->EmailSubject)
            ->setTemplate('MemberInvitationEmail')
            ->populateTemplate(
                ArrayData::create(
                    array(
                        'FirstName' => $this->FirstName,
                        'Surname' => $this->Surname,
                        'Message' => $this->Message,
                        'SiteURL' => $siteURL,
                        'DaysToExpiry' => MemberInvitation::config()->get('days_to_expiry'),
                        'TempHash' => $this->TempHash
                    )
                )
            )
            ->send();
            
        // Disable theme after sending
        Config::inst()->update('SSViewer', 'theme_enabled', false);
        
        return $result;
    }
    public function generateTempHash() {
        $generator = new RandomGenerator();
        return $generator->randomToken('sha1');
    }    
    public function getIsExpired()
    {
        $result = false;

        $days = self::config()->get('days_to_expiry');

        if($days) {
            $time = SS_Datetime::now()->Format('U');
            $ago = abs($time - strtotime($this->Created));
            $rounded = round($ago / 86400);
            if ($rounded > $days) {
                $result = true;
            }            
        }
        return $result;
    }
    public function getIsAccepted()
    {
        return $this->Accepted;
    }

    public function canCreate($member = null)
    {
        return Permission::check('ACCESS_MEMBER_INVITATIONS');
    }
}