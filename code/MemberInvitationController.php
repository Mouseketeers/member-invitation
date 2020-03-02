<?php
class MemberInvitationController extends Controller implements PermissionProvider
{

    private static $allowed_actions = array(
        'index',
        'accept',
        'success',
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
//  public function init()
//     {
//         parent::init();
//         if (!Member::currentUserID()) {
//             $security = Injector::inst()->get(Security::class);
//             $link = $security->Link('login');
//             return $this->redirect(Controller::join_links(
//                 $link,
//                 "?BackURL={$this->Link('index')}"
//             ));
//         }
//     }

    public function index()
    {
        if (!Permission::check('ACCESS_MEMBER_INVITATIONS')) {
            return Security::permissionFailure();
        } else {
            return $this->renderWith(array('MemberInvitation', 'Page'));
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
        } else {
            return $this->redirect($this->Link('notfound'));
        }
        return $this->renderWith(array('MemberInvitation_accept', 'Page'), array('Invite' => $invite));
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
        return $this->renderWith(
            array('MemberInvitation_success', 'Page'),
            array('LoginLink' => $security->Link('login'))
        );
    }

    public function expired()
    {
        return $this->renderWith(array('MemberInvitation_expired', 'Page'));
    }

    public function notfound()
    {
        return $this->renderWith(array('MemberInvitation_notfound', 'Page'));
    }

    private function forbiddenError()
    {
        return $this->httpError(403, _t('MemberInvitation.403_NOTICE', 'You must be logged in to access this page.'));
    }

    private function notFoundError()
    {
        return $this->redirect($this->Link('notfound'));
    }

    /**
     * Ensure that links for this controller use the customised route.
     * Searches through the rules set up for the class and returns the first route.
     *
     * @param  string $action
     * @return string
     */
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
}
