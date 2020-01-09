<?php
class MemberInvitationSecurityAdminExtension extends Extension 
{
    public function updateEditForm(&$form) {
        $fields = $form->Fields();
        $invitationsTab = $fields->findOrMakeTab('Root.Invitations', 'Invitations');
        $invitationsField = GridField::create('MemberInvitations',
            false,
            MemberInvitation::get(),
            GridFieldConfig_RecordEditor::create()
        );
        $invitationsTab->push($invitationsField);

        $invitationsField
            ->getConfig()
            ->getComponentByType('GridFieldDetailForm')
            ->setItemRequestClass('MemberInvitationFieldDetailForm_ItemRequest');

        // to prevent "Call to a member function FormAction() on null" on FormField->Link() 
        $invitationsField->setForm($form);
    }
}