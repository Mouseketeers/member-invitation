<?php

class MemberInvitationController extends Controller
{

	private static $allowed_actions = array(
		'index',
		'accept',
		'success',
		'AcceptForm',
		'expired',
		'notfound',
		'sendInvite'
	);

	public function index()
	{
		return $this->httpError(404, 'Not found');
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

	/**
	 * @param $data
	 * @param Form $form
	 * @return bool|SS_HTTPResponse
	 */
	public function saveInvite($data, Form $form)
	{
		if (!$invite = MemberInvitation::get()->filter('TempHash', $data['HashID'])->first()) {
			return $this->notFoundError();
		}
		if ($form->validate()) {

			$member = Member::create(array('Email' => $invite->Email));
			$form->saveInto($member);

			try {
				if ($member->validate()) {
					
					$invite->Accepted = true;
					$invite->write();

					$member->write();
					// Add member group info
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
			// Delete invitation
			// $invite->delete();

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
		return $this->renderWith(
			array('MemberInvitation_success', 'Page'),
			array('BaseURL' => Director::absoluteBaseURL())
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
