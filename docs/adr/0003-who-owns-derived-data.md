# The client owns its own writes, the server owns what crosses Members

Several values are derived when a record changes: ids, Task keys, `completedAt`, the next Task of a repeating Task, and Activity entries. The frontend already derives all of them. If the server derived them as well, every synced write would produce duplicates. So the client keeps deriving them and sends them in the batch; the server validates the shape, checks that the Member may make the change, and overwrites authorship (`by`) with the signed-in Member. The server alone derives what concerns other Members: Notifications (for Mentions, assignments and Comments on someone else's Task) and the visibility filter for Private projects, which the client cannot be trusted with.

## Consequences

- Ids are client-generated strings; the server accepts any unused id that matches its format and rejects reuse across Workspaces.
- Clients may only change the `read` flag of Notifications, and only their own.
