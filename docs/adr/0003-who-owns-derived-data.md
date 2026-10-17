# The client owns its own writes, the server owns what crosses Members

Several values are derived when a record changes: ids, Task keys, `completedAt`, the next Task of a repeating Task, and Activity entries. The frontend already derives all of them. If the server derived them as well, every synced write would produce duplicates. So the client keeps deriving them and sends them in the batch; the server validates the shape, checks that the Member may make the change, and overwrites authorship (`by`) with the signed-in Member. The server alone derives what concerns other Members: Notifications (for Mentions, assignments and Comments on someone else's Task) and the visibility filter for Private projects, which the client cannot be trusted with.

## Consequences

- Ids are client-generated strings; the server accepts any unused id that matches its format and rejects reuse across Workspaces.
- Clients may only change the `read` flag of Notifications, and only their own.

## Amendment: colliding keys of new Tasks (ticket 03)

Two Members who create a Task in the same Project from the same snapshot both number it from the same `seq` and send the same key. Refusing the second batch would undo that Member's new Task, so the server renumbers instead: when a _new_ Task arrives with a key already taken in its Project, it is stored under the Project's next free key (highest number in use, at least 200, plus one) and `/api/sync` answers `{"ok": true, "keys": {"<taskId>": "WEB-207"}}`. The client applies the returned keys to its Tasks without a reload. Changing an _existing_ Task's key to one in use is still refused with 422. Keys stay client-derived in every other case.
