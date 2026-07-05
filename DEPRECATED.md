# DEPRECATED

Dieses Modul ist stillgelegt. Nachfolger: **`martin3r/platform-inbox`**.

## Warum

- Erstellt am 28.5.2026, eine Woche vor der Inbox (2.6.2026)
- Deckt ein Subset dessen ab, was die Inbox jetzt macht (E-Mail-Threads + Assign-an-Entity)
- Die Inbox macht dasselbe automatisch via `platform-user-connectors`-Webhooks —
  hier lief Import nur manuell via MCP
- Live-Snapshot vor Sunset: 6 Items, davon 0 unread. Faktisch nicht genutzt.

## Was das Modul konnte, das die Inbox (noch) nicht kann

Wenn eines davon relevant wird, sollte es sauber in die Inbox integriert werden,
nicht durch Reaktivierung dieses Moduls:

1. **`ImportLetterTool`** — Papierpost via Claude-OCR importieren. Kein Kanal
   dafür in der Inbox. Bei Bedarf: `channel='letter'` in
   `Platform\Inbox\Enums\Channel` ergänzen, Ingest-Route bauen.
2. **Merge / Split Threads** — Correspondence bot `threads.merge` und
   `threads.split`, wenn Outlook Threads falsch gruppiert (bei
   `AW: AW: WG: [neues Thema]` üblich). Inbox kennt kein Äquivalent.
   Bei Bedarf: `inbox_items.thread_key` Bulk-Update-Verb + UI.
3. **`Encryptable`-Trait** auf Body/Sender/Recipient — Correspondence-Tabellen
   speicherten PII verschlüsselt at rest. `inbox_items.body` steht im Klartext.
   Compliance-relevant für Broich-Catering-Team.

## Was mit Bestandsdaten passiert

- Tabellen `correspondence_threads` und `correspondence_items` bleiben in der DB.
- Existierende 6 Items sind lesbar, aber ohne aktiven ServiceProvider ohne UI.
- Bei Bedarf können die 6 Items manuell in `inbox_items` überführt werden
  (Migration wäre ein 15-Zeilen-Insert-Select, kein Datenverlust-Risiko).
- Physisch löschen erst wenn > 6 Monate Ruhe.
