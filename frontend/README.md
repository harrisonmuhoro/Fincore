# FinCore County frontend

React and strict TypeScript application shell. See the [repository setup guide](../README.md).

```powershell
npm ci
npm run dev
```

Set `VITE_API_BASE_URL` in `.env` to the Laravel API root. Run `npm run typecheck`, `npm run lint`, `npm test`, and `npm run build` before submitting changes.

The frontend contains the Phase 0 navigation shell, API readiness view, shared UI primitives, API client and not-found state. It does not implement business module screens.
