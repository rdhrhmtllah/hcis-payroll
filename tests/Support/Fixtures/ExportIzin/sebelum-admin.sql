
                        WITH
                        cte AS (
                          SELECT
                            CAST(CONCAT(CAST(cti.Tanggal AS DATE),' ', cti.Jam) AS DATETIME) AS tanggal_create,
                            k.Kode_Karyawan,
                            k.Nama,
                            ct.No_Transaksi,
                            ct.Jenis as Tipe_Izin,
                            ct.Tanggal_Cuti_Dari AS Tanggal_Mulai,
                            ct.Tanggal_Cuti_Sampai AS Tanggal_Selesai,
                            ct.Alasan,
                            ct.filePath as Lampiran,
							(select Nama from Karyawan z where z.Kode_Karyawan = ct.Kode_Karyawan) as nama_requester,
                            ISNULL(
                                (
                                SELECT
                                    TOP 1 af.order_flow
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = ct.No_Transaksi
                                    AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NOT NULL
                                    --AND af.Status is NULL
                                ORDER BY
                                    af.order_flow DESC
                                ),
                                0
                            ) as currentFlow,
                            ISNULL(
                                (
                                select
                                    top 1 order_flow
                                from
                                    HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where
                                    a.id = b.Flow_Id
                                    and b.No_Transaksi = ct.No_Transaksi
                                    and a.Kode_Karyawan_Requester = b.Kode_Karyawan
                                    --and a.Status is NULL
                                order by
                                    a.order_flow desc
                                ),
                                0
                            ) as MaxFlow,
                            CASE
                                WHEN ct.Flag_Approval = 'Y' THEN 'Selesai'
                                WHEN ct.Flag_Approval = 'T' THEN 'Ditolak'
                                WHEN ct.Flag_Approval = 'P' THEN 'Ditolak'
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 'Diproses'
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    WHERE
                                    ar.No_Transaksi = ct.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                'NO'
                                )
                            END as status,
                            CASE
                                WHEN ct.Flag_Approval = 'Y' THEN 'DiSetujui'
                                WHEN ct.Flag_Approval = 'P' THEN 'Sistem'
                                WHEN ct.Flag_Approval = 'T' THEN (
                                select
                                    top 1 af.Kode_Karyawan
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = ct.No_Transaksi

									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval = 'T'
                                    --AND af.Status is NULL
                                ORDER BY
                                    af.order_flow
                                )
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 ka.Nama
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    JOIN
                                        Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
                                    WHERE
                                    ar.No_Transaksi = ct.No_Transaksi
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                null
                                )
                            END AS Approver,
                            NULL AS Jam_Mulai,
                            (
                                select
                                STRING_AGG(
                                    CONCAT(
                                    b.Kode_Karyawan,
                                    ': ',
                                    CASE
                                        WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                        ELSE 'N'
                                    END
                                    ),
                                    ', '
                                ) WITHIN GROUP (
                                    ORDER BY
                                    b.order_flow
                                ) AS StatusList
                                from
                                HRIS_Approval_Request a
                                JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                                where
                                a.No_Transaksi = ct.No_Transaksi
								and a.Kode_Karyawan = b.Kode_Karyawan_Requester
                                --and b.Status is NULL
                            ) AS STATUSLIST
                            FROM
                            Karyawan AS k
                            INNER JOIN Transaksi_Cuti_Detail AS ct ON k.Kode_Karyawan = ct.Kode_Karyawan
                            INNER JOIN Transaksi_Cuti AS cti ON ct.No_Transaksi = cti.No_Transaksi
                            WHERE
                             cti.Status is null
                            UNION ALL
                            -- Query untuk Transaksi_Sakit_Izin_Detail
                            SELECT
                            CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
                            k.Kode_Karyawan,
                            k.Nama,
                            tsi.No_Transaksi,
                            tsi.Jenis as Tipe_Izin,
                            tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
                            tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
                            tsi.Alasan,
                            tsi.filePath as Lampiran,
							(select Nama from Karyawan z where z.Kode_Karyawan = tsi.Kode_Karyawan) as nama_requester,
                            ISNULL(
                                (
                                SELECT
                                    TOP 1 af.order_flow
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND af.Status is null
                                    AND ar.Flag_Approval IS NOT NULL
                                ORDER BY
                                    af.order_flow DESC
                                ),
                                0
                            ) as currentFlow,
                            ISNULL(
                                (
                                select
                                    top 1 order_flow
                                from
                                    HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where
                                    a.id = b.Flow_Id
                                    and b.No_Transaksi = tsi.No_Transaksi
									and a.Kode_Karyawan_Requester = b.Kode_Karyawan
                                    --and a.Status is Null
                                order by
                                    a.order_flow desc
                                ),
                                0
                            ) as MaxFlow,
                            CASE
                                WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
                                WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
                                WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 'Diproses'
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                'NO'
                                )
                            END as status,
                            CASE
                                WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
                                WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
                                WHEN tsi.Flag_Approval = 'T' THEN (
                                select
                                    top 1 af.Kode_Karyawan
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi

									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval = 'T'
                                    --AND af.Status is NULL
                                ORDER BY
                                    af.order_flow
                                )
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 ka.Nama
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    JOIN
                                        Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
                                    WHERE
                                    ar.No_Transaksi = tsi.No_Transaksi
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                null
                                )
                            END AS Approver,
                            NULL AS Jam_Mulai,
                            (
                                select
                                STRING_AGG(
                                    CONCAT(
                                    b.Kode_Karyawan,
                                    ': ',
                                    CASE
                                        WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                        ELSE 'N'
                                    END
                                    ),
                                    ', '
                                ) WITHIN GROUP (
                                    ORDER BY
                                    b.order_flow
                                ) AS StatusList
                                from
                                HRIS_Approval_Request a
                                JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                                where
                                a.No_Transaksi = tsi.No_Transaksi
								and a.Kode_Karyawan = b.Kode_Karyawan_Requester
                               -- and b.Status is NULL
                            ) AS STATUSLIST
                            FROM
                            Karyawan AS k
                            INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
                            INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
                            WHERE
                             tsip.Status is null
                            UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
                            SELECT
                            CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
                            k.Kode_Karyawan,
                            k.Nama,
                            ttp.No_Transaksi,
                            ttp.Jenis as Tipe_Izin,
                            ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
                            NULL AS Tanggal_Selesai,
                            ttp.Alasan,
                            ttp.filePath as Lampiran,
							(select Nama from Karyawan z where z.Kode_Karyawan = ttp.Kode_Karyawan) as nama_requester,

                            ISNULL(
                                (
                                SELECT
                                    TOP 1 af.order_flow
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NOT NULL
                                    --AND af.Status is NULL
                                ORDER BY
                                    af.order_flow DESC
                                ),
                                0
                            ) as currentFlow,
                            ISNULL(
                                (
                                select
                                    top 1 order_flow
                                from
                                    HRIS_Approval_Flow a,
                                    HRIS_Approval_Request b
                                where
                                    a.id = b.Flow_Id
                                    and b.No_Transaksi = ttp.No_Transaksi
									and a.Kode_Karyawan_Requester = b.Kode_Karyawan
                                    --and a.Status is NULL
                                order by
                                    a.order_flow desc
                                ),
                                0
                            ) as MaxFlow,
                            CASE
                                WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
                                WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
                                WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 'Diproses'
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
                                    AND ar.Kode_Karyawan = k.Kode_Karyawan
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    --AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                'NO'
                                )
                            END as status,
                            CASE
                                WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
                                WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
                                WHEN ttp.Flag_Approval = 'T' THEN (
                                select
                                    top 1 af.Kode_Karyawan
                                FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi

									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval = 'T'
                                    --AND af.Status is NULL
                                ORDER BY
                                    af.order_flow
                                )
                                ELSE ISNULL(
                                (
                                    SELECT
                                    TOP 1 ka.Nama
                                    FROM
                                    HRIS_Approval_Request ar
                                    JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
                                    JOIN
                                        Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
                                    WHERE
                                    ar.No_Transaksi = ttp.No_Transaksi
									AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
                                    AND ar.Flag_Approval IS NULL
                                    ---AND af.Status is NULL
                                    ORDER BY
                                    af.order_flow
                                ),
                                null
                                )
                            END AS Approver,
                            ttp.Jam AS Jam_Mulai,
                            (
                                select
                                STRING_AGG(
                                    CONCAT(
                                    b.Kode_Karyawan,
                                    ': ',
                                    CASE
                                        WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
                                        ELSE 'N'
                                    END
                                    ),
                                    ', '
                                ) WITHIN GROUP (
                                    ORDER BY
                                    b.order_flow
                                ) AS StatusList
                                from
                                HRIS_Approval_Request a
                                JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
                                where
                                a.No_Transaksi = ttp.No_Transaksi
								and a.Kode_Karyawan = b.Kode_Karyawan_Requester
                                --and b.Status is NULL
                            ) AS STATUSLIST
                            FROM
                            Karyawan AS k
                            INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
                            INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
                            WHERE
                           ttpp.Status is null
                        )
                       SELECT *
                        FROM cte
                        WHERE MaxFlow <> 0
                        AND (
                                -- Kalau Tanggal_Selesai NULL, cek hanya di tanggal mulai
                                (CAST(Tanggal_Selesai AS DATETIME) IS NULL
                                    AND CAST(Tanggal_Mulai AS DATETIME) BETWEEN ? AND ?)

                                -- Kalau ada tanggal selesai, cek overlap interval
                                OR (Tanggal_Selesai IS NOT NULL
                                    AND CAST(Tanggal_Mulai AS DATETIME) <= ?
                                    AND CAST(Tanggal_Selesai AS DATETIME) >= ?)
                            )
                        ORDER BY Tanggal_Mulai;
            