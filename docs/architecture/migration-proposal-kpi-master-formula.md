# Migration Proposal: KPI Master Formula

Status: `EXECUTED_AND_VERIFIED`

## Scope
Path alignment only for the formula controller and local formula pages/components. Route file remains shared with Satuan Formula and only the Formula controller import changes.

## Paths
```text
app/Http/Controllers/FormulaKpiController.php
→ app/Http/Controllers/KPI/MasterData/Scoring/Formula/FormulaKpiController.php
resources/js/Pages/KPI/modules/formula/masterFormula.vue
→ resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaListPage.vue
resources/js/Pages/KPI/modules/formula/formTambahFormula.vue
→ resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaCreatePage.vue
resources/js/Pages/KPI/modules/formula/formEditFormula.vue
→ resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaEditPage.vue
resources/js/Pages/KPI/modules/formula/components/*
→ resources/js/Pages/KPI/MasterData/Scoring/Formula/components/*
```

## Preserved
URLs `/formula-kpi*`, `/api/v1/formula-kpi/*`, middleware `auth, check.jabatan:MasterFormula`, route names, formula table contracts, `KpiScoringService`, and Satuan Formula.

## Verification
PHP syntax passed; Composer class loading passed; frontend build passed; old active-reference scan passed; `git diff --check` passed. No dedicated Formula feature test was found.

## Rollback
`git revert <migration-commit>`; no database rollback required.
