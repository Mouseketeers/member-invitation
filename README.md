# Member invitation Module for SilverStripe CMS #
A SilverStripe module to handle user invitations in SilverStripe CMS. Send invitations by e-mail a let users sign up and choose their own password.

## Requirements
 * SilverStripe 3.7

## Installation
composer require mouseketeers/member-invitation

## Configuration

MemberInvitation:
  default_from_email: 'email@example.com'
  default_email_subject: 'Invitation to join my site'
  default_message: '<p>Join my site.</p>'
  default_groups: 'blog-users,content-authors'