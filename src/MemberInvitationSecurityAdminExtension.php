<?php

namespace Mouseketeers\SilverstripeMemberInvitation;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Mouseketeers\SilverstripeMemberInvitation\MemberInvitationFieldDetailForm_ItemRequest;

use SilverStripe\Forms\GridField\GridFieldPrintButton;


class MemberInvitationSecurityAdminExtension extends Extension 
{
    public function updateEditForm($form) {
        $fields = $form->Fields();
        $invitationsTab = $fields->findOrMakeTab('Root.Invitations', 'Invitations');
        $invitationsField = GridField::create('MemberInvitations',
            '',
            MemberInvitation::get(),
            GridFieldConfig_RecordEditor::create()
        );
        $invitationsTab->push($invitationsField);

        $invitationsField
            ->getConfig()
            ->getComponentByType('SilverStripe\Forms\GridField\GridFieldDetailForm')
            ->setItemRequestClass(MemberInvitationFieldDetailForm_ItemRequest::class);

        $invitationsField->setForm($form);
    }
}