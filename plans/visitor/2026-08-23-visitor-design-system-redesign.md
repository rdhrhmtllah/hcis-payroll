# Visitor Design-System Redesign Checkpoint

Status: `IMPLEMENTED_MANUAL_REVIEW_PENDING`

## Scope

- Module: `VISITOR` (`Id_Grup_Fitur=4`, development `Web_HRIS`).
- Verified pages: Permit, Tamu, Approval, Verifikator, Laporan, dan Approval Matrix.
- Verification date: 2026-08-23.
- Change class: related frontend-only redesign after production hardening.

## Boundaries

- Preserve all endpoint, payload, permission, lifecycle, and idempotency behavior.
- Replace legacy page chrome with HCIS design-system components.
- Prefer existing `Hcis*` primitives and module-scoped composition.
- Shared component extensions must be additive and backward compatible.
- New components are allowed only for Visitor-specific workspace or business presentation.
- No route, backend, database, or global layout changes.

## Component mapping

| Legacy pattern | Target |
|---|---|
| `legacy/header.vue` | `HcisHeader` through `VisitorWorkspace` |
| `legacy/quickStat.vue` | `HcisStatGroup` |
| `legacy/toggleSectionV2.vue` | `HcisTabs` |
| hand-written white surface | `HcisCard` |
| duplicated approval policy card | `VisitorApprovalContext` + `HcisContextBanner` |
| raw status/icon decoration | `HcisBadge` and `HcisIcon` |

## Verification

- focused component tests for new module components;
- compile every changed Visitor SFC;
- design-system registry/import and Inertia page gates;
- production build;
- desktop/mobile browser review when a browser and verified UAT target are available.

## Implementation result

- The five Visitor workspaces now use the HCIS header, stat, card, context banner, and tab primitives.
- Visitor-scoped compatibility adapters replace the legacy filter, table, dialog, and toggle APIs without changing their callers' events or slot contracts.
- Confirmation and preview dialogs in the Tamu and Permit forms use the Visitor dialog adapter.
- The shared `DetailApprovalModal` remains unchanged because it is owned outside VISITOR and is reused by other modules; changing that shared boundary requires a separate impacted-feature checkpoint.

## Automated verification result

- Focused Vitest: `3` files, `7` tests passed.
- Vue SFC compile: `23` VISITOR files passed.
- Inertia page gate: `126` rendered pages, `0` broken references.
- Production Vite build: passed (`4837` modules transformed).
- Frontend import verifier exited successfully but inspected `0` paths on Windows due its existing absolute-path handling defect; it is not counted as positive verification.

## Remaining manual verification

- Review desktop and mobile layouts for Tamu, Permit, Approval, Verifikator, and Laporan using a verified development/UAT target.
- Exercise filter, pagination, detail dialog, submit confirmation, approval action, and verification action flows with representative data.
