 select 
                a.Kode_Karyawan,
                v.nama_divisi,
                v.nama_sub_divisi,
                k.UserID_Absen,
                k.Nama,
                a.Jam_Masuk,
                a.Jam_Keluar,
                a.ID_Shift,
                a.Nama_Shift,
                a.JENIS_SHIFT_DIPILIH as Jenis_Shift,
                a.Tanggal as Periode,
                a.ECOFX AS Jam_selesai_absen
            from N_HRIS_FN_GetStatusHariRange('001', ?, ?, ?) a JOIN Karyawan k ON a.Kode_Karyawan = k.Kode_Karyawan JOIN View_Divisi_Sub_Divisi v ON k.ID_Divisi_Sub_Divisi = v.ID_DIVISI_SUB_DIVISI