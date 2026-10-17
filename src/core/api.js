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

/* A multipart upload through XMLHttpRequest, which (unlike fetch) reports how much has been sent. */
function upload(path, fields, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const form = new FormData();
    for (const [k, v] of Object.entries(fields)) if (v != null) form.append(k, v);
    xhr.open('POST', API_URL + path);
    xhr.withCredentials = true;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.onprogress = e => e.lengthComputable && onProgress?.(e.loaded / e.total);
    xhr.onload = () => {
      let json = null;
      try {
        json = JSON.parse(xhr.responseText);
      } catch {
        /* not JSON: a proxy's or PHP's own error page */
      }
      if (xhr.status >= 200 && xhr.status < 300) resolve(json);
      else reject(new ApiError(xhr.status, json));
    };
    xhr.onerror = () => reject(new ApiError(0));
    xhr.send(form);
  });
}

export const api = {
  me: () => call('GET', '/me'),
  login: (email, password) => call('POST', '/login', { email, password }),
  logout: () => call('POST', '/logout'),
  bootstrap: () => call('GET', '/bootstrap'),
  sync: ops => call('POST', '/sync', { ops }),
  invite: token => call('GET', `/invites/${encodeURIComponent(token)}`),
  acceptInvite: (token, name, password) => call('POST', `/invites/${encodeURIComponent(token)}/accept`, { name, password }),
  resendInvite: memberId => call('POST', `/members/${encodeURIComponent(memberId)}/invite`),
  /** Stores `file` as the File `id` of the Project (and Task); resolves with its record. `onProgress(0…1)` follows the upload. */
  uploadFile: (file, { id, project, task }, onProgress) => upload('/files', { file, id, project, task }, onProgress),
  copyFile: (id, copyId) => call('POST', `/files/${encodeURIComponent(id)}/copy`, { id: copyId }),
};

/** Where a File's content is downloaded from (the browser sends the session cookie itself). */
export const fileUrl = id => `${API_URL}/files/${encodeURIComponent(id)}/content`;
