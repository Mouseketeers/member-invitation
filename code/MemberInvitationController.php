<?php
class MemberInvitationController extends Controller implements PermissionProvider
{

    private static $allowed_actions = array(
        'index',
        'accept',
        'success',
        'accepted',
        'expired',
        'notfound',
        'sendInvite',
        'AcceptForm',
        'InvitationForm'
    );

    public function providePermissions()
    {
        return array(
            'ACCESS_MEMBER_INVITATIONS' => array(
                'name' => _t('MemberInvitationController.ACCESS_PERMISSIONS', 'Allow sending user invitations'),
                'category' => _t('MemberInvitationController.CMS_ACCESS_CATEGORY', 'Invitations')
            )
        );
    }
    public function index()
    {
        if (!Permission::check('ACCESS_MEMBER_INVITATIONS')) {
            return Security::permissionFailure();
        } else {
            $responseController = $this->getResponseController();
            return $responseController->renderWith(
                array('MemberInvitation', 'MemberInvitation','Page', 'BlankPage'),
                array('InvitationForm' => $this->InvitationForm())
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

        if (!$form->validate()) {
            $form->sessionMessage(
                _t(
                    'MemberInvitation.SENT_INVITATION_VALIDATION_FAILED',
                    'At least one error occured while trying to save your invite: {error}',
                    array('error' => $form->getValidator()->getErrors()[0]['fieldName'])
                ),
                'bad'
            );
            return $this->redirectBack();
        }

        $invite = MemberInvitation::create();
        $invite->DateSent = SS_Datetime::now()->Rfc2822();

        $form->saveInto($invite);

        // todo: avoid duplicating this logic

        if(!$invite->InvitedByID) {
            $invite->InvitedByID = Member::currentUserID();
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
                array('email' => $data['Email'])
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
        $responseController = $this->getResponseController();
        return $responseController->renderWith(
            $this->getTemplatesFor('accept'),
            array('Invite' => $invite, 'AcceptForm' => $this->AcceptForm())
        );
    }

    public function AcceptForm() {
        return MemberInvitationAcceptForm::create($this, 'AcceptForm');
    }
    public function acceptInvite($data, Form $form)
    {
        if (!$invite = MemberInvitation::get()->filter('TempHash', $data['HashID'])->first()) {
            return $this->notFoundError();
        }
        if ($form->validate()) {

            $member = Member::create(array('Email' => $invite->Email));

            $form->saveInto($member);

            $this->extend('updateAcceptInvite', $this, $data, $form, $invite, $member);

            try {
                if ($member->validate()) {
                    $member->write();
                    $groups = explode(',', $invite->Groups);
                    foreach ($groups as $groupCode) {
                        $member->addToGroupByCode($groupCode);
                    }
                }
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            // $invite->delete();
            $invite->Accepted = true;
            $invite->write();
            
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
        $responseController = $this->getResponseController();
        return $responseController->renderWith(
            $this->getTemplatesFor('success'),
            array('LoginLink' => $security->Link('login'))
        );
    }
    public function expired()
    {
        $responseController = $this->getResponseController();
        return $responseController->renderWith(
            $this->getTemplatesFor('expired')
        );
    }
    public function accepted()
    {
        $security = Injector::inst()->get(Security::class);
        $responseController = $this->getResponseController();
        return $this->renderWith(
            $this->getTemplatesFor('accepted'),
            array('LoginLink' => $security->Link('login'))
        );
    }    
    public function notfound()
    {
        $responseController = $this->getResponseController();
        return $this->renderWith(
            $this->getTemplatesFor('notfound')
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
    public function Link($action = null)
    {
        if ($url = array_search(get_called_class(), (array)Config::inst()->get('Director', 'rules'))) {
            // Check for slashes and drop them
            if ($indexOf = stripos($url, '/')) {
                $url = substr($url, 0, $indexOf);
            }
            return $this->join_links($url, $action);
        }
    }
    protected function getResponseController() {
        if(!class_exists('SiteTree')) return $this;
        $tmpPage = new Page();
        $tmpPage->URLSegment = "invite";
        // Disable ID-based caching  of the log-in page by making it a random number
        $tmpPage->ID = -1 * rand(1,10000000);
        $controller = Page_Controller::create($tmpPage);
        $controller->setDataModel($this->model);
        $controller->init();
        return $controller;
    }    
    public function getTemplatesFor($action) {
        return array("MemberInvitation_{$action}", 'MemberInvitation','Page', 'BlankPage');
    }    
}
