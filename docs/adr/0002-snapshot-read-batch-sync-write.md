# One snapshot read and one batch sync write, in the frontend's data shape

The frontend renders synchronously from one in-memory object (`S.data`) and changes it in about sixty places. Rewriting every action to call a resource API would touch nearly all of the UI code. Instead the backend speaks the frontend's own data shape: `GET /api/bootstrap` returns the whole visible Workspace as `S.data`, and `POST /api/sync` takes an ordered batch of `put` and `delete` operations per collection and applies them in one transaction. The frontend computes that batch by diffing `S.data` against the last synced copy whenever it saves, so every write path, including undo and drag and drop, is covered by one hook.

## Consequences

- Writes are last-write-wins per record. A rejected batch makes the client reload the snapshot.
- The snapshot grows with the Workspace; paging is a later concern.
- A resource-per-endpoint REST API can be added beside this later without changing storage.
