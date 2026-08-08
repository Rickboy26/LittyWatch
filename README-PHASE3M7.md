# LittyWatch V5.2 — Phase 3M7

Fix voor servers zonder werkende DNS-resolutie richting Guild Wars Wiki.
De browser resolveert het image-host IP via DNS-over-HTTPS en geeft het IP mee aan de LittyWatch same-origin proxy. PHP gebruikt CURLOPT_RESOLVE, zodat TLS/hostname-validatie intact blijft maar server-DNS wordt omzeild. De mapper blijft uitsluitend zeer sterke visuele matches opslaan.
