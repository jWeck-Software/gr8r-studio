# Gr8r Studio

A project management workspace where a team plans projects, tracks tasks and talks about the work.

## Language

### People and access

**User**:
A person who can sign in, identified by an email address and a password. Whoever signs up starts their own Workspace, as its Owner, once they are verified. A User who forgot their password chooses a new one with a link emailed to them, which works once for 30 minutes; a new password signs them out everywhere else.
_Avoid_: account, login
In code, "account" stays as a name for what a User does for themselves: signing up, verifying, passwords, starting and deleting their Workspace (`App\Account\Accounts`, `AccountController`, `AccountRefused`), and the Invite link's `account` key (whether the address has a User already). Text about the domain says User.

**Verified**:
A User is verified once they proved the email address is theirs, by opening the link sent to it when they signed up, the link of an Invite or a password reset link. An unverified User cannot sign in; signing up again before verifying starts over with a new link.
_Avoid_: confirmed, activated

**Workspace**:
The shared space a team works in; it owns every project, task, team and file. Only an Owner deletes it, with everything in it; its Members' Users stay, without a Workspace.
_Avoid_: organization, tenant, company

**Member**:
A User's place in one Workspace, carrying a role, a team and a title. A Member can exist before its User has accepted the invite.
_Avoid_: user (when the workspace role is meant), seat

**Role**:
What a Member may do in a Workspace: Owner, Admin, Member or Guest.
_Avoid_: permission level

**Invite**:
An offer to an email address to become a Member of a Workspace, sent by an Owner or Admin as a link that works once and expires after a week; the Member stays `invited` until it is accepted. Sending it again replaces the link. Whoever accepts chooses their name and a password and becomes a User, or, when the address belongs to a User already, joins with that User's password.

**Team**:
A named group of Members inside a Workspace, such as Design or Engineering.
_Avoid_: group, department

### Work

**Project**:
A body of work with a key, a lead, a due date and its own Tasks.
_Avoid_: board, space

**Private project**:
A Project only its own Members can see, together with everything inside it.
_Avoid_: restricted project, hidden project

**Project order**:
The order of Projects in the sidebar, shared by the whole Workspace. Projects a Member can't see keep their place in it.

**Task**:
One piece of work inside a Project, with a status, an assignee, a priority and dates.
_Avoid_: ticket, issue, card, item

**Task key**:
The human-readable name of a Task, made of its Project's key and a number, like `WEB-103`.
_Avoid_: task number, ref

**Subtask**:
A checklist step inside a Task. It has no status, assignee or dates of its own until it is promoted to a Task.
_Avoid_: child task, checklist item

**Dependency**:
A Task that has to be finished before another Task can start.
_Avoid_: blocker, link

**Milestone**:
A named date inside a Project.

**Archive**:
Taking a Task or Project out of everyday views without deleting it; it can be restored.
_Avoid_: soft delete, trash

**Saved view**:
A named set of filters, sort and grouping on one Project that everyone in the Workspace can open.
_Avoid_: filter preset

### Conversation and awareness

**Comment**:
A message a Member writes on a Task.

**Mention**:
A reference to a Member inside a Comment, written as `@Full Name`.

**Reaction**:
An emoji a Member attaches to a Comment.

**Activity**:
The Workspace's record of who changed what, and when.
_Avoid_: audit log, history, feed

**Notification**:
A message to one Member that something concerning them happened, such as a Mention or an assignment. It is read or unread.
_Avoid_: alert, inbox item

### Content

**File**:
A document uploaded to a Project, optionally attached to one Task.
_Avoid_: asset, document, upload

**Attachment**:
A File as seen from the Task it is attached to.

**Event**:
A dated meeting on a Project's calendar.
_Avoid_: meeting, appointment

### Personal settings

**Favorite**:
A Project or Task one Member starred; it is that Member's own and changes nothing for anyone else.
_Avoid_: bookmark, pin

**Preferences**:
How one User wants the app to look and behave, such as theme, density and date format.
_Avoid_: settings (too broad)
