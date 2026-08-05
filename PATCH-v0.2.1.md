# LittyWatch v0.2.1 collector fix

Fixes parsing of the Kamadan GWToolbox `/m` JSON payload.
The live endpoint uses compact keys:

- `s` sender
- `m` message
- `t` timestamp
- `h` message hash

Replace `bootstrap.php`, commit and push, then pull on the VPS.
