<?php

namespace Mouseketeers\SilverstripeMemberInvitation;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Subsites\Model\Subsite;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Admin\SecurityAdmin;
use SilverStripe\GraphQL\Controller;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Control\Director;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Control\Email\Email;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\Forms\FormAction;

class MemberInvitation extends DataObject 
{
    private static $table_name = 'MemberInvitation';
    public static $allowed_actions = [
        'ItemEditForm'
    ];

    private static $db = [
        'FirstName' => 'Varchar',
        'Surname' => 'Varchar',
        'Email' => 'Varchar(254)',
        'FromEmail' => 'Varchar(254)',
        'FromEmailName' => 'Varchar(254)',
        'EmailSubject' => 'Varchar(254)',
        'Message' => 'HTMLText',
        'Groups' => 'Text',
        'TempHash' => 'Varchar',
        'DateSent' => 'Datetime',
        'Accepted' => 'Boolean'
    ];

    private static $has_one = [
        'InvitedBy' => Member::class
    ];
    
    private static $indexes = [
        'Email' => true,
        'TempHash' => true
    ];

    private static $summary_fields = [
        'FirstName',
        'Surname',
        'Email',
        'Status',
        'DateSent'
    ];

    private static $default_sort = 'Created DESC';

    public function populateDefaults()
    {
        parent::populateDefaults();

        $defaultFromEmail = self::config()->get('default_from_email');
        $this->FromEmail = ($defaultFromEmail) ? $defaultFromEmail :  Email::config()->get('admin_email');

        $defaultFromEmailName = self::config()->get('default_from_email_name');
        $this->FromEmailName = ($defaultFromEmailName) ? $defaultFromEmailName :  NULL;

        $defaultEmailSubject = self::config()->get('default_email_subject');
        $this->EmailSubject = ($defaultEmailSubject) ? $defaultEmailSubject : 'Invitation to join '.SiteConfig::current_site_config()->Title;

        $defaultMessage = self::config()->get('default_message');
        $this->Message = ($defaultMessage) ? $defaultMessage : 'You have been invited to join '.SiteConfig::current_site_config()->Title;

        if(class_exists('Subsite')) {
            $this->SubsiteID = Subsite::currentSubsiteID();
        }

        if($defaultGroups = self::config()->get('default_groups')) {
            $this->Groups = $defaultGroups;
        }

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
            [
                'Email',
                'FromEmail',
                'EmailSubject',
                'Groups'
            ]
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

        if(!$this->InvitedByID) {
            $currentUser = Security::getCurrentUser();
            $this->InvitedByID = $currentUser ? $currentUser->ID : null;
        }
        
        if(!$this->TempHash) {
            $this->TempHash = $this->generateTempHash();
        }

        $groups = Group::get();
        $groupsMap = [];
        
        foreach ($groups as $group) {
            $groupsMap[$group->Code] = $group->getBreadcrumbs(' > ');
        }
        
        asort($groupsMap);
       
        $fields->replaceField(
            'Groups',
            ListboxField::create('Groups', Injector::inst()->get(Group::class)->i18n_plural_name())
                ->setSource($groupsMap)
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
                ReadonlyField::create(
                    'AcceptLink',
                    'Accept Link',
                    $siteURL.'invite/accept/'.$this->TempHash
                )
                ->setRightTitle('Link sent in invitation. You can also copy this and send it in an email of your own (e.g. if a person complains that they haven\'t received the invitation).')
               ,
               'DateSent'
            );            
        }

        $fields->insertBefore(
            ReadonlyField::create(
                'InvitedByReadOnlyField',
                'Invited By',
                $this->InvitedBy()->getTitle()
            ),
            'DateSent'
        );        

        $fields->dataFieldByName('DateSent')
            ->setReadonly(true);
        
        $fields->replaceField('TempHash', 
            HiddenField::create('TempHash', 'TempHash')
        );
        
        $fields->replaceField('Accepted', 
            HiddenField::create('Accepted', 'Accepted')
        );
        $fields->replaceField('InvitedByID',
            HiddenField::create('InvitedByID', 'InvitedByID')
        );

        return $fields;
    }

    public function validate() 
    {
        $valid = parent::validate();

        if(!$this->ID) {
            if (Member::get()->filter('Email', $this->Email)->first()) {
                $valid->addError(
                    _t('MemberInvitation.MEMBER_ALREADY_EXISTS', 'An member with this e-mail is already registered.')
                );
                return $valid;
            }
            if ($invite = self::get()->filter('Email', $this->Email)->first()) {
                $valid->addError(
                    _t('MemberInvitation.INVITE_EXISTS', 'An invitation with this e-mail already exists.')
                );
            }
        }
        return $valid;
    }
    public function sendInvitation()
    {
        if($subsiteID = $this->SubsiteID) {
            $subsite = Subsite::get()->byID($subsiteID);
            $siteURL = 'http://'.$subsite->getPrimarySubsiteDomain()->Domain.'/';
        }
        else {
            $siteURL = Director::absoluteBaseURL();
        }
        
        return Email::create()
            ->setFrom($this->FromEmail, $this->FromEmailName)
            ->setTo($this->Email)
            ->setSubject($this->EmailSubject)
            ->setHTMLTemplate('Email\\MemberInvitationEmail')
            ->setData(
                ArrayData::create(
                    [
                        'FirstName' => $this->FirstName,
                        'Surname' => $this->Surname,
                        'Message' => $this->Message,
                        'SiteURL' => $siteURL,
                        'DaysToExpiry' => MemberInvitation::config()->get('days_to_expiry'),
                        'TempHash' => $this->TempHash
                    ]
                )
            )
            ->send();
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
            $time = DBDatetime::now()->getTimestamp();
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
    public function canCreate($member = null, $context = [])
    {
        return Permission::check('ACCESS_MEMBER_INVITATIONS');
    }
}