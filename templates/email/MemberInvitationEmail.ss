<% if $FirstName %><p><%t MemberInvitationEmail.HEADING "Dear {name} {surname}" name=$FirstName surname=$Surname %></p><% end_if %>
$Message
<p><a href="{$SiteURL}invite/accept/{$TempHash}"><%t MemberInvitationEmail.ACCEPT "Click here to accept the invitation" %></a></p>