# Gr8r Studio

A project management workspace where a team plans projects, tracks tasks and talks about the work.

## Language

### People and access

**User**:
A person who can sign in, identified by an email address and a password.
_Avoid_: account, login

**Workspace**:
The shared space a team works in; it owns every project, task, team and file.
_Avoid_: organization, tenant, company

**Member**:
A User's place in one Workspace, carrying a role, a team and a title. A Member can exist before its User has accepted the invite.
_Avoid_: user (when the workspace role is meant), seat

**Role**:
What a Member may do in a Workspace: Owner, Admin, Member or Guest.
_Avoid_: permission level

**Invite**:
An offer to an email address to become a Member of a Workspace; the Member stays `invited` until it is accepted.

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
