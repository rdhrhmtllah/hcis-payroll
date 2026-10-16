# Baseline uji JS — 2026-09-01

Diukur pada cabang `feat/sop/harderning`, commit `85be172e`, dengan `npx vitest run` (seluruh repo).

**190 berkas / 1.505 uji → 37 berkas merah / 130 uji merah.**

## Kenapa dokumen ini ada

Baseline uji sebelumnya (`2026-09-01-sebab-uji-merah.txt`) HANYA mencakup
`php artisan test` untuk sebagian `tests/Feature`. Ia tidak pernah menyentuh
`tests/Unit` maupun satu pun uji JS. Akibatnya angka "45 uji merah" yang
dipakai sepanjang audit ini **bukan keadaan sebenarnya** — ia keadaan satu
sudut yang kebetulan diukur.

Kegagalan di bawah ini SUDAH ADA sebelum pekerjaan hardening; tak satu pun
berasal darinya. Yang baru hanyalah bahwa sekarang ia terlihat.

## Yang paling layak didahulukan

`designSystemGuards.test.js` (16) dan `designMdTokens.test.js` (2) adalah
penjaga RATCHET sistem desain — penjaga yang justru dibuat supaya utang CSS
tak bertambah. Penjaga yang merah berhenti menjaga apa pun, dan ratchet yang
mati tak berbunyi ketika angkanya naik.

## Sebaran

```
     16 resources/js/components/ui/designSystemGuards.test.js
     16 resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js
     12 resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenPickerModal.test.js
     10 resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiDuaLayar.test.js
      6 resources/js/Pages/LMS/SOP/Shared/components/ui/SopCabutAksesDialog.test.js
      5 resources/js/Pages/LMS/SOP/Shared/components/ui/SopVersiRingkas.test.js
      5 resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/KaryawanDrawer.test.js
      5 resources/js/composables/ESS/Employee/DataCollection/formatBidang.test.js
      3 resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitDigitalPass.test.js
      3 resources/js/Pages/LMS/SOP/Shared/components/ui/SopPanelProfilTipe.test.js
      3 resources/js/Pages/LMS/SOP/Shared/components/ui/ItemFields.test.js
      3 resources/js/Pages/LMS/SOP/Governance/Admin/SopMonitoring.test.js
      3 resources/js/components/ui/HcisTimeline.test.js
      3 resources/js/components/ui/HcisKomponenAnak.test.js
      3 resources/js/components/ui/HcisDateTimePicker.test.js
      3 resources/js/components/ui/HcisDateRangePicker.test.js
      3 resources/js/components/ui/HcisComponents.test.js
      2 resources/js/Pages/VISITOR/Workflow/Reporting/ExportStudio/exportStudioVisitor.test.js
      2 resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitForm.test.js
      2 resources/js/Pages/LMS/SOP/Shared/components/listing/SopListingDokumen.test.js
      2 resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/ringkasan/panel/PanelAntreanKerja.test.js
      2 resources/js/components/ui/HcisFormFeedback.test.js
      2 resources/js/components/ui/HcisFilterField.test.js
      2 resources/js/components/ui/HcisFileViewer.test.js
      2 resources/js/components/ui/HcisDateRangeTimePicker.test.js
      2 resources/js/components/ui/designMdTokens.test.js
      1 resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js [ resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.test.js ]
      1 resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/TabRingkasan.test.js
      1 resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/ringkasan/panel/PanelLeadTime.test.js
      1 resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.test.js
      1 resources/js/Pages/LMS/Content/ContentManagement/components/ui/UploadKontenModal.test.js
      1 resources/js/Pages/ESS/Employee/DataCollection/Wizard.test.js
      1 resources/js/components/ui/HcisTimePicker.test.js
      1 resources/js/components/ui/HcisSelect.test.js
      1 resources/js/components/ui/HcisMessage.test.js
      1 resources/js/components/ui/HcisDialog.test.js
      1 resources/js/components/ui/HcisDatePicker.test.js
```

## Yang BELUM diketahui

Sebab tiap kegagalan belum ditelusuri. Dokumen ini angka, bukan diagnosis.
