# ADR-002: DDL eksplisit tanpa migration framework

- Status: Accepted
- Context: Perubahan production wajib tercatat melalui SQL Tracker.
- Decision: Agent menghasilkan DDL forward dan companion drop script, tanpa menjalankan migration.
- Consequence: Status development, tracker, dan production harus dilacak terpisah.
