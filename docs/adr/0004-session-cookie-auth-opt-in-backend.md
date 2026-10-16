# Session-cookie sign-in, same origin, backend mode opt-in

The API authenticates with Symfony's session cookie (JSON login), not bearer tokens: the frontend is served from the same origin through the Vite dev proxy or a reverse proxy in production, so the cookie stays `HttpOnly` and no token ever reaches JavaScript. Backend mode is switched on by setting `VITE_API_URL` at build time; without it the frontend keeps working as the static localStorage demo, so the existing Vercel deployment is unaffected.
