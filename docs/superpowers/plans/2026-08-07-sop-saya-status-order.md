# SOP Saya — Default Sort by Status Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the "SOP Saya" list default-sort by package status (Baru Diassign → In Progress → Selesai, tie-broken by nearest deadline) instead of pure urgency, while keeping the old sort available as an explicit option.

**Architecture:** Single-file frontend change in `resources/js/Pages/SOP/modules/saya/SopSaya.vue`. No backend/API change — `Status_Paket` is already present on every item returned by `SopProgressReadService::tugasSaya()`.

**Tech Stack:** Vue 2 Options API (Inertia page component).

## Global Constraints

- Do not hardcode `Status_Paket` string literals in the component — derive ordering from the existing `STATUS_PAKET` export in `resources/js/Pages/SOP/support/constants.js` (see its header comment: "Jangan hardcode string status di komponen — impor dari sini").
- Do not touch `grupKategori`, the Grup tab filters (`perlu_dibaca`/`terlambat`/`diperbarui`/`selesai`), or any admin-facing SOP page (`SopMonitoring.vue`, `SopTeam.vue`).
- No new backend query or API change.

---

### Task 1: Add "status" sort mode and make it the default

**Files:**
- Modify: `resources/js/Pages/SOP/modules/saya/SopSaya.vue:304` (import), `:314` (module-level rank constant), `:338` (default sort value), `:377-384` (sort dropdown options), `:398-415` (`visibleItems` sort switch)

**Interfaces:**
- Consumes: `STATUS_PAKET` export from `resources/js/Pages/SOP/support/constants.js` (keys `BELUM`, `PROGRESS`, `SELESAI`, in that declared order) — already used elsewhere in the SOP module for status badges.
- Produces: new `listFilters.sort` value `"status"`, selectable via the existing "Urutkan" dropdown and used as the initial/default value. No other component depends on this.

- [ ] **Step 1: Add a manual test checklist (no automated test harness exists for this component)**

There is no existing frontend test suite for `SopSaya.vue` (per the design spec's Testing section). Verification for this task is manual, done in Step 5 after the change — write down what "pass" means before touching code:

1. Open `/sop/saya` with no category selected → cards render grouped: all `Status_Paket: "BELUM"` items first, then all `"PROGRESS"`, then all `"SELESAI"`; within each group, ascending days-to-deadline.
2. Click into a single category (rak) → the same status-grouped order holds for that category's items.
3. Dropdown "Urutkan" shows a "Tahapan (Baru → Proses → Selesai)" option, selected by default; switching to "Paling mendesak" and back restores the status-grouped order.

- [ ] **Step 2: Import `STATUS_PAKET` and derive a rank list**

In `resources/js/Pages/SOP/modules/saya/SopSaya.vue`, change the import on line 304:

```js
import { SOP_URL, PRIORITAS, STATUS_PAKET, kategoriTheme } from "../../support/constants";
```

Directly below the existing `const PRIO_RANK = { KRITIS: 0, TINGGI: 1, NORMAL: 2 };` (line 314), add:

```js
const STATUS_ORDER = Object.keys(STATUS_PAKET); // ["BELUM", "PROGRESS", "SELESAI"]
```

- [ ] **Step 3: Change the default sort value**

Line 338, change:

```js
listFilters: { search: "", viewMode: "grid", prioritas: "", sort: "urgensi" },
```

to:

```js
listFilters: { search: "", viewMode: "grid", prioritas: "", sort: "status" },
```

- [ ] **Step 4: Add the dropdown option and the sort branch**

In `filterConfig()`, the `sort` filter's `options` array (lines 377-384) currently reads:

```js
{ key: "sort", label: "Urutkan", type: "select", col: 3, placeholder: "Paling mendesak", options: [
    { label: "Paling mendesak", value: "urgensi" },
    { label: "Tenggat terdekat", value: "tenggat" },
    { label: "Progres tertinggi", value: "progres_tinggi" },
    { label: "Progres terendah", value: "progres_rendah" },
    { label: "Nama A–Z", value: "nama" },
    { label: "Prioritas", value: "prioritas" },
] },
```

Replace it with (new option first, since it's now the default; placeholder updated to match):

```js
{ key: "sort", label: "Urutkan", type: "select", col: 3, placeholder: "Tahapan pengerjaan", options: [
    { label: "Tahapan (Baru → Proses → Selesai)", value: "status" },
    { label: "Paling mendesak", value: "urgensi" },
    { label: "Tenggat terdekat", value: "tenggat" },
    { label: "Progres tertinggi", value: "progres_tinggi" },
    { label: "Progres terendah", value: "progres_rendah" },
    { label: "Nama A–Z", value: "nama" },
    { label: "Prioritas", value: "prioritas" },
] },
```

In `visibleItems()` (the `switch (f.sort)` block, lines 398-415), add a `"status"` case before `default`:

```js
switch (f.sort) {
    case "status":
        arr.sort((a, b) => {
            const d = STATUS_ORDER.indexOf(a.Status_Paket) - STATUS_ORDER.indexOf(b.Status_Paket);
            return d !== 0 ? d : this.daysLeft(a) - this.daysLeft(b);
        }); break;
    case "tenggat":
        arr.sort((a, b) => this.daysLeft(a) - this.daysLeft(b)); break;
    case "progres_tinggi":
        arr.sort((a, b) => b.Persen - a.Persen); break;
    case "progres_rendah":
        arr.sort((a, b) => a.Persen - b.Persen); break;
    case "nama":
        arr.sort((a, b) => (a.Nama_Paket || "").localeCompare(b.Nama_Paket || "")); break;
    case "prioritas":
        arr.sort((a, b) => rank(a.Prioritas) - rank(b.Prioritas)); break;
    default: // urgensi
        arr.sort((a, b) => {
            const d = this.daysLeft(a) - this.daysLeft(b);
            return d !== 0 ? d : rank(a.Prioritas) - rank(b.Prioritas);
        });
}
```

`STATUS_ORDER.indexOf(...)` returns `-1` for an unexpected/missing `Status_Paket`, which sorts that item first rather than crashing — acceptable since the backend always populates this field (see design spec's Error Handling section).

- [ ] **Step 5: Manually verify against the Step 1 checklist**

Run the dev server if not already running (`npm run dev` or the project's existing Vite command), open `/sop/saya` as a user with at least one SOP in each of BELUM/PROGRESS/SELESAI, and walk through the 3 checks from Step 1. Fix any discrepancy before proceeding.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/SOP/modules/saya/SopSaya.vue
git commit -m "feat(sop): default-sort SOP Saya by status (baru -> progress -> selesai)"
```
