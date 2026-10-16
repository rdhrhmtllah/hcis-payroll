/* Change ID: DML-2026-169-jadwal-rbac-sinkron-hak
 * RBAC Fase 3a: jadwalkan rbac:sinkron-hak tiap jam (menit ke-17) supaya
 * N_HRIS_Role_Hak mengikuti hibah role selama modul masih LAMA/BAYANGAN.
 * Idempoten. Keterangan <= 100 karakter (varchar(100)). */
SET NOCOUNT ON;
IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Scheduler_Configs WHERE Command = 'rbac:sinkron-hak')
    INSERT INTO dbo.N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
    VALUES ('rbac:sinkron-hak', '17 * * * *', 'Asia/Jakarta', 'Y', 'RBAC: sinkron hak role dengan hibah (modul LAMA/BAYANGAN), tiap jam', GETDATE());
