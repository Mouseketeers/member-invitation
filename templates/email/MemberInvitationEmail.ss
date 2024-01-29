<% if $FirstName %>
	<p><%t MemberInvitationEmail.HEADING "Dear {name} {surname}" name=$FirstName surname=$Surname %></p>
<% end_if %>
<p>$Message.RAW</p>
<p><a href="{$SiteURL}invite/accept/{$TempHash}"><%t MemberInvitationEmail.ACCEPT "Click here to accept the invitation" %></a></p>