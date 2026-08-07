# LittyWatch V5.2 Phase 3M — Kamadan Dataset Foundation

- Nieuwe `/admin/dataset` pagina: volume, periode, spelers, parserdekking, herhaalde patronen en reviewgroepen.
- `/admin/dataset/export`: NDJSON met raw message + huidige structured offer labels voor AI/evaluatie.
- CLI export: `php tools/maintenance/export-training-dataset.php`.
- `messages.raw_payload` en `messages.collector_version` zijn voorbereid zodat toekomstige collectors bronpayload/versionering kunnen bewaren.
- Bestaande `messages` blijven de immutable bron voor reparses; structured offers blijven afgeleide data.

Na deploy: open `/admin/dataset`, verzamel meer Kamadan-data via de bestaande collector en exporteer periodiek een trainingssnapshot.
