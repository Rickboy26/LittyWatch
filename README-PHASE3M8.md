# Phase 3M8 — Inventory icon transport fallback

- Strengthens Guild Wars Wiki icon transport.
- cURL uses explicit HTTP/1.1, TLS verification, Referer and browser-like headers.
- Adds direct TLS socket fallback via the browser-resolved IP when cURL cannot retrieve a PNG.
- Mapper now surfaces the first concrete transport error (HTTP status / curl errno / bytes) instead of only counting read failures.
- No inventory icon pack needs to be re-uploaded.
