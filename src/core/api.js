/* ---------- api: the backend's HTTP endpoints ---------- */
/* Backend mode is on when the build sets VITE_API_URL (e.g. `/api`, proxied by the Vite dev
   server). Without it the app is the localStorage demo and nothing here is called.
   Every call resolves with the parsed JSON body, or rejects with an ApiError: `status` is the
   HTTP status, or 0 when the server could not be reached. */
export const API_URL = import.meta.env.VITE_API_URL || '';
export const BACKEND = !!API_URL;

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || (status ? `The server answered ${status}.` : "Can't reach the server. Check your connection."));
    this.status = status;
    this.code = body?.error || (status ? 'error' : 'network');
  }
}

async function call(method, path, body) {
  let res;
  try {
    res = await fetch(API_URL + path, {
      method,
      credentials: 'same-origin',
      headers: body ? { 'Content-Type': 'application/json', Accept: 'application/json' } : { Accept: 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError(0);
  }
  const json = res.status === 204 ? null : await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(res.status, json);
  return json;
}

export const api = {
  me: () => call('GET', '/me'),
  login: (email, password) => call('POST', '/login', { email, password }),
  logout: () => call('POST', '/logout'),
  bootstrap: () => call('GET', '/bootstrap'),
  sync: ops => call('POST', '/sync', { ops }),
};
