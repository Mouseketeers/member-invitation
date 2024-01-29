<?php

namespace Mouseketeers\SilverstripeMemberInvitation;

use SilverStripe\Control\Controller;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\ContentController;
use Mouseketeers\SilverstripeMemberInvitation\MemberInvitation;
use Mouseketeers\SilverstripeMemberInvitation\MemberInvitationForm;
use SilverStripe\Security\Group;




class MemberInvitationController extends Controller implements PermissionProvider
{

    private static $url_segment = 'invite';

    private static $allowed_actions = [
        'index',
        'accept',
        'success',
        'accepted',
        'expired',
        'notfound',
        'sendInvite',
        'AcceptForm',
        'InvitationForm'
    ];

    public function providePermissions()
    {
        return [
            'ACCESS_MEMBER_INVITATIONS' => [
                'name' => _t(
                    'MemberInvitationController.ACCESS_PERMISSIONS',
                    'Allow sending member invitations'
                ),
                'category' => _t(
                    'MemberInvitationController.CMS_ACCESS_CATEGORY',
                    'Member Invitations'
                )
            ]
            
        ];
    }
    public function init()
    {
        parent::init();

        if (!Security::getCurrentUser()) 
        {
            $action = $this->getRequest()->param('Action');
            if(!$action || $action === 'index' || $action === 'InvitationForm')
            {
                $security = Injector::inst()->get(Security::class);
                $link = $security->Link('login');
                return $this->redirect(Controller::join_links(
                    $link,
                    "?BackURL={$this->Link('index')}"
                ));
            }
        }
    }

    public function index()
    {
        if (!Permission::check('ACCESS_MEMBER_INVITATIONS')) {
            return Security::permissionFailure();
        } else {
            return $this->renderWith(
                ['MemberInvitation', SiteTree::class],
                ['InvitationForm' => $this->InvitationForm()]
            );
        }
    }

    public function InvitationForm()
    {
        return MemberInvitationForm::create($this, 'InvitationForm');
    }

    public function sendInvite($data, Form $form)
    {

        if (!Permission::check('ACCESS_MEMBER_INVITATIONS')) {
            $form->sessionMessage(
                _t(
                    'MemberInvitation.PERMISSION_FAILURE',
                    "You don't have permission to create user invitations"
                ),
                'bad'
            );
            return $this->redirectBack();
        }

        if (!$form->validationResult()->isValid()) {
            $form->sessionMessage(
                _t(
                    'MemberInvitation.SENT_INVITATION_VALIDATION_FAILED',
                    'At least one error occured while trying to save your invite: {error}',
                    ['error' => $form->getValidator()->getErrors()[0]['fieldName']]
                ),
                'bad'
            );
            return $this->redirectBack();
        }

        $invite = MemberInvitation::create();
        $invite->DateSent = DBDatetime::now()->Rfc2822();

        $form->saveInto($invite);

        if(!$invite->InvitedByID) {
            $currentUser = Security::getCurrentUser();
            $invite->InvitedByID = $currentUser ? $currentUser->ID : null;
        }
        
        if(!$invite->TempHash) {
            $invite->TempHash = $invite->generateTempHash();
        }        

        try {
            $invite->write();
        } catch (ValidationException $e) {
            $form->sessionMessage(
                $e->getMessage(),
                'bad'
            );
            return $this->redirectBack();
        }
        
        $invite->sendInvitation();

        $form->sessionMessage(
            _t(
                'MemberInvitation.SENT_INVITATION',
                'An invitation was sent to {email}.',
                ['email' => $data['Email']]
            ),
            'good'
        );
        return $this->redirectBack();
    }

    public function accept()
    {
        if (!$hash = $this->getRequest()->param('ID')) {
            return $this->forbiddenError();
        }
        if ($invite = MemberInvitation::get()->filter('TempHash', $hash)->first()) {
            if ($invite->getIsExpired()) {
                return $this->redirect($this->Link('expired'));
            }
            else {
                if($invite->getIsAccepted()) {
                     return $this->redirect($this->Link('accepted'));
                }
            }
        } else {
            return $this->redirect($this->Link('notfound'));
        }
        return $this->renderWith(
            ['MemberInvitation_accept', SiteTree::class]
        );
    }
    public function AcceptForm()
    {
        return MemberInvitationAcceptForm::create($this, 'AcceptForm');
    }
    public function saveInvite($data, Form $form)
    {
        if (!$invite = MemberInvitation::get()->filter(
            'TempHash',
            $data['HashID']
        )->first()) {
            return $this->notFoundError();
        }
        if ($form->validationResult()->isValid()) {
            $member = Member::create(['Email' => $invite->Email]);
            $form->saveInto($member);
            try {
                if ($member->validate()) {
                    $member->write();
                    // Add user group info
                    $groups = explode(',', $invite->Groups);
                    foreach (Group::get()->filter(['Code' => $groups]) as $group) {
                        $group->Members()->add($member);
                    }
                }
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            // Delete invitation
            $invite->delete();
            return $this->redirect($this->Link('success'));
        } else {
            $form->sessionMessage(
                Convert::array2json($form->getValidator()->getErrors()),
                'bad'
            );
            return $this->redirectBack();
        }
    }
    public function success()
    {
        $security = Injector::inst()->get(Security::class);
        return $this->renderWith(
            ['MemberInvitation_success', SiteTree::class],
            ['LoginLink' => $security->Link('login')]
        );
    }
    public function expired()
    {
        return $this->renderWith(
            ['MemberInvitation_expired', SiteTree::class]
        );
    }
    public function accepted()
    {
        $security = Injector::inst()->get(Security::class);
        return $this->renderWith(
            ['MemberInvitation_accepted', SiteTree::class],
            ['LoginLink' => $security->Link('login')]
        );
    }    
    public function notfound()
    {
        return $this->renderWith(
            ['MemberInvitation_notfound', SiteTree::class]
        );
    }    
    private function forbiddenError()
    {
        return $this->httpError(403, _t('MemberInvitation.403_NOTICE', 'You must be logged in to access this page.'));
    }

    private function notFoundError()
    {
        return $this->redirect($this->Link('notfound'));
    }
    protected function getResponseController() {
        if(!class_exists(SiteTree::class)) return $this;
        $tmpPage = SiteTree::create();
        $tmpPage->URLSegment = "invite";
        // Disable ID-based caching  of the log-in page by making it a random number
        $tmpPage->ID = -1 * rand(1,10000000);
        $controller = ContentController::create($tmpPage);
        $controller->setDataModel($this->model);
        $controller->init();
        return $controller;
    }    
    public function getTemplatesFor($action) {
        return ["MemberInvitation_{$action}", MemberInvitation::class, SiteTree::class, 'BlankPage'];
    }    
}