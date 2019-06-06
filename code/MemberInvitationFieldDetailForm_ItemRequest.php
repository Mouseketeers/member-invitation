<?php
class MemberInvitationFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
	public static $allowed_actions = array (
		'doSendInvitation',
		'ItemEditForm'
	);
	// public function Breadcrumbs($unlinked = false)
	function ItemEditForm()
	{
		$form = parent::ItemEditForm();
		$formActions = $form->Actions();
		$button = FormAction::create('doSendInvitation');
		$button->setTitle('Send Invitation');
		$formActions->push($button);
		$form->setActions($formActions);
		return $form;
	}

	public function doSendInvitation($data, $form) {

		$now = SS_Datetime::now()->Rfc2822();
		$this->record->DateSent = $now;
		$this->doSave($data, $form);
		
		if($subsiteID = $this->record->SubsiteID) {
			$subsite = Subsite::get()->byID($subsiteID);
			$siteURL = 'http://'.$subsite->getPrimarySubsiteDomain()->Domain.'/';
		}
		else {
			$siteURL = Director::absoluteBaseURL();
		}

		// enable theme to use custom template
		Config::inst()->update('SSViewer', 'theme_enabled', true);

		Email::create()
			->setFrom($this->record->FromEmail)
			->setTo($this->record->Email)
			->setSubject($this->record->EmailSubject)
			->setTemplate(array('MemberInvitationEmail'))
			->populateTemplate(
				ArrayData::create(
					array(
						'FirstName' => $this->record->FirstName,
						'Surname' => $this->record->Surname,
						'Message' => $this->record->Message,
						'SiteURL' => $siteURL,
						'DaysToExpiry' => MemberInvitation::config()->get('days_to_expiry'),
						'TempHash' => $this->record->TempHash
					)
				)
			)
			->send();

		// disable theme again so that it doesn't affect the CMS
		Config::inst()->update('SSViewer', 'theme_enabled', false);
		
		$controller = $this->getToplevelController();
		 // Force a content refresh
		$controller->getRequest()->addHeader('X-Pjax', 'Content');
		//redirect back to admin section
		$backLink = $this->getBacklink();
		return $controller->redirect($backLink, 302); 
	} 
}