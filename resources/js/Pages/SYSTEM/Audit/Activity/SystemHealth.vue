<template>
    <div class="sys-health">
        <!-- Toolbar -->
        <div class="sh-toolbar">
            <div class="sh-toolbar__left">
                <span class="sh-title"><i class="bi bi-heart-pulse-fill"></i> Kesehatan Sistem &amp; Diagnostik</span>
                <el-select v-model="hours" size="small" style="width: 132px" @change="reloadAll">
                    <el-option :value="1" label="1 jam" />
                    <el-option :value="6" label="6 jam" />
                    <el-option :value="24" label="24 jam" />
                    <el-option :value="72" label="3 hari" />
                    <el-option :value="168" label="7 hari" />
                </el-select>
            </div>
            <div class="sh-toolbar__right">
                <el-switch v-model="autoRefresh" active-text="Auto 30s" size="small" @change="toggleAuto" />
                <el-button size="small" round :loading="loading" @click="reloadAll">
                    <i class="bi bi-arrow-repeat me-1"></i> Muat ulang
                </el-button>
            </div>
        </div>

        <!-- Not provisioned -->
        <div v-if="summary.provisioned === false" class="sh-empty">
            <i class="bi bi-database-exclamation"></i>
            <h4>Tabel diagnostik belum tersedia</h4>
            <p>{{ summary.message || 'Jalankan DDL 2026_07_02_request_diagnostics.sql lalu set LOG_CHANNEL=stderr.' }}</p>
            <code>database/sql/2026_07_02_request_diagnostics.sql</code>
        </div>

        <template v-else>
            <!-- ══ Pantau Langsung (HANYA lingkungan pengembangan) ══════════
                 Membaca DMV saat itu juga, bukan tabel rekaman. Digerbangi
                 tiga kunci di server; saat tertutup ia MENYEBUTKAN sebabnya
                 alih-alih diam, supaya tak ada yang menunggu pemantau yang
                 sebenarnya mati. -->
            <div class="sh-live" :class="{ 'is-off': !live.aktif }">
                <div class="sh-live__head">
                    <span class="sh-live__title">
                        <i class="bi bi-broadcast"></i> Pantau Langsung
                        <em v-if="live.aktif">· tiap {{ liveInterval }} dtk</em>
                    </span>
                    <div class="sh-live__act">
                        <el-tag v-if="live.aktif && !live.atribusi_aktif" type="warning" size="small" effect="plain">
                            Atribusi mati — nyalakan REQUEST_DIAGNOSTICS_TAG_SESSION
                        </el-tag>
                        <el-switch
                            v-model="livePolling"
                            :disabled="live.aktif === false"
                            active-text="Nyalakan"
                            size="small"
                            @change="togglePantau"
                        />
                    </div>
                </div>

                <p v-if="!live.aktif" class="sh-live__off">
                    <i class="bi bi-shield-lock"></i>
                    {{ live.alasan || 'Pemantauan realtime tidak aktif.' }}
                    <small>Ini disengaja: di produksi tiap sampel memakai satu koneksi dari pool
                        yang sama yang sedang habis.</small>
                </p>

                <template v-else>
                    <div class="sh-live__angka">
                        <div><strong>{{ live.permintaan?.length ?? 0 }}</strong><span>permintaan aktif</span></div>
                        <div><strong>{{ live.koneksi?.total_sesi ?? 0 }}</strong><span>sesi pengguna</span></div>
                        <div :class="{ 'is-bad': (live.koneksi?.tidur_bertransaksi ?? 0) > 0 }">
                            <strong>{{ live.koneksi?.tidur_bertransaksi ?? 0 }}</strong>
                            <span>tidur + transaksi terbuka</span>
                        </div>
                        <div :class="{ 'is-bad': (live.rantai_blokir?.length ?? 0) > 0 }">
                            <strong>{{ live.rantai_blokir?.length ?? 0 }}</strong><span>sedang tertahan</span>
                        </div>
                    </div>

                    <div v-if="live.rantai_blokir?.length" class="sh-live__blokir">
                        <div v-for="(r, i) in live.rantai_blokir" :key="i" class="sh-live__baris">
                            <span class="sh-live__korban">{{ r.jalur_korban || ('sesi #' + r.session_korban) }}</span>
                            <i class="bi bi-arrow-left"></i>
                            <span class="sh-live__akar">{{ r.jalur_akar || ('sesi #' + r.session_akar) }}</span>
                            <el-tag size="small" type="info" effect="plain">{{ r.wait_type || 'tanpa wait' }}</el-tag>
                            <el-tag v-if="r.akar_menganggur" size="small" type="danger" effect="dark">
                                akar menganggur{{ r.umur_transaksi_akar_detik != null ? ` · trx ${r.umur_transaksi_akar_detik} dtk` : '' }}
                            </el-tag>
                            <!-- Kalimat SQL akarnya: inilah yang mengubah "sesi #59"
                                 jadi kode yang bisa dibuka orangnya. -->
                            <code v-if="r.sql_akar" class="sh-live__sql">{{ r.sql_akar }}</code>
                        </div>
                    </div>
                    <p v-else class="sh-live__aman">
                        Tidak ada yang tertahan saat ini. Kalau "Login timeout expired" tetap terjadi,
                        yang habis koneksinya — bukan lock-nya.
                    </p>

                    <!-- Lock yang MENUNGGU: sedikit barisnya, dan tiap baris
                         langsung menamai objek yang jadi rebutan. Lock yang sudah
                         GRANT tidak menyakiti siapa pun, jadi ia turun ke bawah. -->
                    <div v-if="live.lock_menunggu?.length" class="sh-live__tunggu">
                        <span class="sh-live__tunggu-judul">Diperebutkan sekarang:</span>
                        <span v-for="(l, i) in live.lock_menunggu" :key="i">
                            {{ l.objek }} <em>minta {{ l.mode_diminta }}</em> ×{{ l.jumlah }}
                        </span>
                    </div>

                    <div v-if="live.transaksi_menganggur?.length" class="sh-live__nganggur">
                        <span class="sh-live__tunggu-judul">Transaksi terbuka tanpa kueri berjalan:</span>
                        <div v-for="(m, i) in live.transaksi_menganggur.slice(0, 5)" :key="i" class="sh-live__nganggur-baris">
                            <b>{{ m.jalur || ('sesi #' + m.session_id) }}</b>
                            <span>diam {{ m.diam_detik }} dtk · trx {{ m.umur_transaksi_detik ?? '?' }} dtk · {{ m.program_name }}</span>
                            <code v-if="m.sql_terakhir">{{ m.sql_terakhir }}</code>
                        </div>
                    </div>

                    <div v-if="live.lock_per_objek?.length" class="sh-live__objek">
                        <span v-for="(l, i) in live.lock_per_objek.slice(0, 8)" :key="i">
                            {{ l.objek }} <em>{{ l.tipe_sumber }}/{{ l.mode }}</em> ×{{ l.jumlah }}
                        </span>
                    </div>
                </template>
            </div>

            <!-- ══ Rekam Sesi Uji ═════════════════════════════════════════
                 Menjawab yang tak bisa dijawab sisi basis data: RUTE mana yang
                 menjalankan kueri itu. dm_exec_query_stats tahu sebuah kueri
                 mahal dan sering, tetapi tidak tahu endpoint pemanggilnya. -->
            <div v-if="live.aktif !== false" class="sh-live sh-rekam" :class="{ 'is-on': rekam.merekam }">
                <div class="sh-live__head">
                    <span class="sh-live__title">
                        <i class="bi bi-record-circle"></i> Rekam Sesi Uji
                        <em v-if="rekam.sesi">· {{ rekam.sesi }}{{ rekam.merekam ? ' (berjalan)' : '' }}</em>
                    </span>
                    <div class="sh-live__act">
                        <el-button v-if="!rekam.merekam" size="small" round type="danger" @click="aksiRekam('mulai')">
                            <i class="bi bi-record-fill me-1"></i> Mulai rekam
                        </el-button>
                        <el-button v-else size="small" round @click="aksiRekam('berhenti')">
                            <i class="bi bi-stop-fill me-1"></i> Berhenti
                        </el-button>
                        <el-button size="small" round :loading="rekam.memuat" @click="aksiRekam()">
                            <i class="bi bi-arrow-repeat me-1"></i> Muat hasil
                        </el-button>
                    </div>
                </div>

                <p class="sh-berat__ket">
                    Tekan <b>Mulai rekam</b>, jalankan satu fitur, lalu <b>Berhenti</b>.
                    Setiap kueri yang dijalankan aplikasi dikelompokkan menurut rutenya.
                    Permintaan tanpa kueri tidak dicatat.
                </p>

                <div v-if="rekam.total?.permintaan" class="sh-live__angka">
                    <div><strong>{{ rekam.total.permintaan }}</strong><span>permintaan</span></div>
                    <div><strong>{{ rekam.total.kueri }}</strong><span>kueri</span></div>
                    <div><strong>{{ Math.round(rekam.total.ms) }}</strong><span>ms di database</span></div>
                </div>

                <div v-for="(r, i) in rekam.rute" :key="i" class="sh-rekam__rute">
                    <div class="sh-rekam__kepala">
                        <b>{{ r.rute }}</b>
                        <span>{{ r.permintaan }} permintaan · {{ r.kueri }} kueri · {{ r.ms }} ms</span>
                        <!-- Puncak kueri dalam SATU permintaan adalah tanda N+1,
                             dan ia hilang begitu angkanya dirata-ratakan. -->
                        <el-tag
                            :type="r.kueri_terbanyak >= 20 ? 'danger' : 'info'"
                            size="small"
                            effect="plain"
                        >{{ r.kueri_per_permintaan }}/permintaan · puncak {{ r.kueri_terbanyak }}</el-tag>
                    </div>
                    <div v-for="(d, j) in r.daftar" :key="j" class="sh-rekam__sql">
                        <span class="sh-rekam__n" :class="{ 'is-bad': d.n >= 5 }">{{ d.n }}×</span>
                        <span class="sh-rekam__ms">{{ d.ms }} ms</span>
                        <code>{{ d.sql }}</code>
                    </div>
                </div>
            </div>

            <!-- ══ Kueri Terberat ═════════════════════════════════════════
                 Pelengkap Pantau Langsung: potret hanya melihat yang SEDANG
                 berjalan, jadi kueri cepat-tapi-sering tak pernah tertangkap —
                 padahal gabungan itulah yang menghabiskan CPU. -->
            <div v-if="live.aktif !== false" class="sh-live sh-berat">
                <div class="sh-live__head">
                    <span class="sh-live__title">
                        <i class="bi bi-fire"></i> Kueri Terberat
                        <em v-if="berat.ada_garis_dasar">· sejak {{ berat.garis_dasar_pada }}</em>
                        <em v-else>· seumur server</em>
                    </span>
                    <div class="sh-live__act">
                        <el-select v-model="beratUrut" size="small" style="width: 140px" @change="ambilBerat()">
                            <el-option value="cpu" label="CPU total" />
                            <el-option value="durasi" label="Durasi total" />
                            <el-option value="baca" label="Logical reads" />
                            <el-option value="eksekusi" label="Jumlah eksekusi" />
                        </el-select>
                        <el-button size="small" round :loading="berat.memuat" @click="ambilBerat()">
                            <i class="bi bi-arrow-repeat me-1"></i> Muat
                        </el-button>
                        <el-button size="small" round type="primary" @click="ambilBerat('tandai')">
                            <i class="bi bi-flag me-1"></i> Tandai mulai uji
                        </el-button>
                        <el-button v-if="berat.ada_garis_dasar" size="small" round @click="ambilBerat('hapus')">
                            Lepas penanda
                        </el-button>
                    </div>
                </div>

                <p class="sh-berat__ket">
                    <template v-if="berat.ada_garis_dasar">
                        Menampilkan <b>selisih sejak penanda</b> — hanya kueri yang benar-benar bergerak.
                    </template>
                    <template v-else>
                        Angka kumulatif sejak rencananya masuk cache. Tekan <b>Tandai mulai uji</b>
                        sebelum menyisir fitur, supaya yang tampil hanya kueri yang uji itu picu.
                    </template>
                </p>

                <div v-if="berat.baris?.length" class="sh-berat__tabel">
                    <div class="sh-berat__kepala">
                        <span>eksekusi</span><span>CPU</span><span>rata</span><span>baca/eks</span><span>kueri</span>
                    </div>
                    <div v-for="(b, i) in berat.baris" :key="i" class="sh-berat__baris">
                        <span class="sh-berat__n">{{ Number(b.eksekusi).toLocaleString('id-ID') }}</span>
                        <span class="sh-berat__n">{{ Number(b.cpu_ms).toLocaleString('id-ID') }} ms</span>
                        <span class="sh-berat__n">{{ b.rata_ms }} ms</span>
                        <span class="sh-berat__n" :class="{ 'is-bad': b.baca_per_eksekusi > 5000 }">
                            {{ Number(b.baca_per_eksekusi).toLocaleString('id-ID') }}
                        </span>
                        <span class="sh-berat__sql">
                            <el-tag v-if="b.baru" size="small" type="success" effect="plain">baru</el-tag>
                            <em v-if="b.objek && b.objek !== '(ad-hoc)'">{{ b.objek }} · </em>{{ b.sql_text }}
                        </span>
                    </div>
                </div>
                <p v-else-if="!berat.memuat" class="sh-live__aman">Belum ada data. Tekan <b>Muat</b>.</p>
            </div>

            <!-- KPI cards -->
            <div class="sh-kpis">
                <div class="sh-kpi is-slate">
                    <div class="sh-kpi__ic"><i class="bi bi-collection"></i></div>
                    <div><strong>{{ fmt(summary.total) }}</strong><span>Request lambat/error</span><small>{{ hoursLabel }}</small></div>
                </div>
                <div class="sh-kpi is-danger" :class="{ 'is-hot': summary.err_5xx > 0 }">
                    <div class="sh-kpi__ic"><i class="bi bi-x-octagon-fill"></i></div>
                    <div><strong>{{ fmt(summary.err_5xx) }}</strong><span>Error 5xx</span><small>HTTP ≥ 500</small></div>
                </div>
                <div class="sh-kpi is-amber" :class="{ 'is-hot': summary.conn_problem > 0 }">
                    <div class="sh-kpi__ic"><i class="bi bi-plug-fill"></i></div>
                    <div><strong>{{ fmt(summary.conn_problem) }}</strong><span>Gagal ambil koneksi</span><small>hang &amp; 0 query</small></div>
                </div>
                <div class="sh-kpi is-violet" :class="{ 'is-hot': summary.slow_query > 0 }">
                    <div class="sh-kpi__ic"><i class="bi bi-hourglass-split"></i></div>
                    <div><strong>{{ fmt(summary.slow_query) }}</strong><span>Query lambat / lock</span><small>db ≥ {{ fmt(summary.slow_ms) }}ms</small></div>
                </div>
                <div class="sh-kpi is-primary">
                    <div class="sh-kpi__ic"><i class="bi bi-speedometer2"></i></div>
                    <div><strong>{{ fmt(summary.avg_total_ms) }}<em>ms</em></strong><span>Rata-rata durasi</span><small>maks {{ fmt(summary.max_total_ms) }}ms</small></div>
                </div>
                <div class="sh-kpi is-rose" :class="{ 'is-hot': summary.blocking_events > 0 }">
                    <div class="sh-kpi__ic"><i class="bi bi-lock-fill"></i></div>
                    <div><strong>{{ fmt(summary.blocking_events) }}</strong><span>Blocking DB</span><small>{{ summary.last_blocking_at ? fmtTime(summary.last_blocking_at) : 'tak ada' }}</small></div>
                </div>
            </div>

            <!-- Verdict banner -->
            <div class="sh-verdict" :class="verdict.tone">
                <i :class="verdict.icon"></i>
                <div>
                    <strong>{{ verdict.title }}</strong>
                    <span>{{ verdict.detail }}</span>
                </div>
            </div>

            <!-- Requests panel -->
            <div class="sh-panel">
                <div class="sh-panel__head">
                    <h5><i class="bi bi-list-columns-reverse"></i> Request lambat &amp; error</h5>
                    <div class="sh-filters">
                        <el-radio-group v-model="kelas" size="small" @change="loadRequests(1)">
                            <el-radio-button label="all">Semua</el-radio-button>
                            <el-radio-button label="conn">Koneksi</el-radio-button>
                            <el-radio-button label="query">Query/Lock</el-radio-button>
                            <el-radio-button label="error">5xx</el-radio-button>
                        </el-radio-group>
                        <el-input v-model="q" size="small" placeholder="cari path / user / SQL" clearable style="width: 220px"
                            @keyup.enter="loadRequests(1)" @clear="loadRequests(1)">
                            <template #prefix><i class="bi bi-search"></i></template>
                        </el-input>
                    </div>
                </div>

                <el-table :data="requests.items" v-loading="requests.loading" size="small" class="sh-table"
                    :row-class-name="rowClass" empty-text="Tidak ada request lambat/error pada rentang ini 🎉">
                    <el-table-column label="Waktu" width="150">
                        <template #default="{ row }"><span class="muted">{{ fmtTime(row.Created_At) }}</span></template>
                    </el-table-column>
                    <el-table-column label="Kelas" width="118">
                        <template #default="{ row }">
                            <el-tag :type="kelasTag(row.Kelas).type" size="small" effect="dark" round>
                                <i :class="kelasTag(row.Kelas).icon"></i> {{ kelasTag(row.Kelas).text }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Path" min-width="200">
                        <template #default="{ row }">
                            <span class="sh-method" :data-m="row.Method">{{ row.Method }}</span>
                            <code class="sh-path">{{ row.Path }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column label="User" width="120">
                        <template #default="{ row }">{{ row.Kode_Karyawan || '—' }}</template>
                    </el-table-column>
                    <el-table-column label="Status" width="82" align="center">
                        <template #default="{ row }">
                            <span class="sh-status" :class="row.Http_Status >= 500 ? 'is-5xx' : (row.Http_Status >= 400 ? 'is-4xx' : 'is-ok')">{{ row.Http_Status }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Total" width="92" align="right">
                        <template #default="{ row }"><strong>{{ fmt(row.Total_Ms) }}</strong><em class="u">ms</em></template>
                    </el-table-column>
                    <el-table-column label="Konek" width="92" align="right">
                        <template #default="{ row }">
                            <span :class="{ 'sh-hot': row.Kelas === 'conn' }">{{ row.Conn_Acquire_Ms === null ? '—' : fmt(row.Conn_Acquire_Ms) }}</span><em class="u">ms</em>
                        </template>
                    </el-table-column>
                    <el-table-column label="Query" width="120" align="right">
                        <template #default="{ row }">
                            <span :class="{ 'sh-hot': row.Kelas === 'query' }">{{ fmt(row.Db_Query_Ms) }}<em class="u">ms</em></span>
                            <small class="sh-qc">×{{ row.Db_Query_Count }}</small>
                        </template>
                    </el-table-column>
                    <el-table-column label="SQL terlambat" min-width="220">
                        <template #default="{ row }">
                            <code v-if="row.Slowest_Query_Sql" class="sh-sql" :title="row.Slowest_Query_Sql">{{ row.Slowest_Query_Sql }}</code>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="sh-pager">
                    <el-pagination background layout="total, prev, pager, next" :total="requests.total"
                        :page-size="requests.perPage" :current-page="requests.page" @current-change="loadRequests" />
                </div>
            </div>

            <!-- DB blocking panel -->
            <div class="sh-panel">
                <div class="sh-panel__head">
                    <h5><i class="bi bi-diagram-3-fill"></i> Blocking &amp; lock DB <small class="muted">(Layer 3 · db:health-snapshot)</small></h5>
                </div>
                <div v-if="dbHealth.provisioned === false" class="sh-note">
                    <i class="bi bi-info-circle"></i> {{ dbHealth.message }}
                </div>
                <div v-else-if="!dbHealth.rows.length" class="sh-note ok">
                    <i class="bi bi-check-circle-fill"></i> Tidak ada blocking terdeteksi pada {{ hoursLabel }}. (Aktifkan scheduler <code>db:health-snapshot</code> untuk pemantauan kontinu.)
                </div>
                <el-table v-else :data="dbHealth.rows" size="small" class="sh-table" max-height="360">
                    <el-table-column label="Waktu" width="150">
                        <template #default="{ row }"><span class="muted">{{ fmtTime(row.Captured_At) }}</span></template>
                    </el-table-column>
                    <el-table-column label="Sesi" width="72" align="center">
                        <template #default="{ row }">{{ row.Session_Id }}</template>
                    </el-table-column>
                    <el-table-column label="Diblok oleh" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.Blocking_Session_Id" type="danger" size="small" effect="dark">← {{ row.Blocking_Session_Id }}</el-tag>
                            <el-tag v-else type="warning" size="small" effect="plain">penahan</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="Wait" width="150">
                        <template #default="{ row }"><span class="sh-wait">{{ row.Wait_Type || '—' }}</span> <small class="muted">{{ fmt(row.Wait_Time_Ms) }}ms</small></template>
                    </el-table-column>
                    <el-table-column label="Login / Host" width="180">
                        <template #default="{ row }"><span class="muted">{{ row.Login_Name }}</span><br><small class="muted">{{ row.Host_Name }}</small></template>
                    </el-table-column>
                    <el-table-column label="Trace" width="120">
                        <template #default="{ row }"><code class="sh-sql">{{ row.Context_Info || '—' }}</code></template>
                    </el-table-column>
                    <el-table-column label="SQL" min-width="220">
                        <template #default="{ row }"><code class="sh-sql" :title="row.Sql_Text">{{ row.Sql_Text || '—' }}</code></template>
                    </el-table-column>
                </el-table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useElementBridge } from '@/components/ui/useElementBridge';

/*
 * Halaman PILOT untuk jembatan gaya Element Plus.
 *
 * Sampai sekarang seluruh element-plus-bridge.css berlingkup `.ds-preview`,
 * jadi ia hanya berlaku di /design-system. Di halaman nyata, komponen Element
 * tampil dengan gaya bawaannya — tidak mengenal token kita dan tidak ikut
 * berganti saat tema berganti.
 *
 * Halaman ini yang pertama mengadopsinya. Dipilih karena padat komponen Element
 * (36 di 353 baris) tapi cukup kecil untuk diperiksa seluruhnya dengan mata.
 * Halaman lain BELUM berubah sama sekali; menambahkan baris ini adalah cara
 * satu halaman ikut, satu per satu, setelah rupanya diperiksa.
 */
useElementBridge();

const hours = ref(24);
const kelas = ref('all');
const q = ref('');
const autoRefresh = ref(false);
const loading = ref(false);
let timer = null;

const summary = reactive({ provisioned: null });
const requests = reactive({ loading: false, items: [], page: 1, perPage: 25, total: 0 });
const dbHealth = reactive({ provisioned: null, rows: [] });

/*
 * Pantau Langsung. Dipisah dari reloadAll() dengan sengaja: ia berdenyut jauh
 * lebih cepat daripada panel lain, dan menyeretnya ke dalam muat-ulang biasa
 * akan membuat tiap denyut ikut menarik tiga endpoint lain.
 */
const live = reactive({ aktif: null, alasan: '', atribusi_aktif: false });
const livePolling = ref(false);

/*
 * Kueri terberat. TIDAK ikut berdenyut: angkanya kumulatif dan bergerak
 * lambat, jadi menariknya tiap beberapa detik hanya menambah beban tanpa
 * menambah informasi. Ditarik saat diminta.
 */
const berat = reactive({ memuat: false, baris: [], ada_garis_dasar: false, garis_dasar_pada: null });
const beratUrut = ref('cpu');

/* Rekam sesi uji — atribusi per rute, yang tak bisa diberikan sisi DB. */
const rekam = reactive({ memuat: false, merekam: false, sesi: null, rute: [], total: {} });

async function aksiRekam(aksi = '') {
    rekam.memuat = true;
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/recording', {
            params: aksi ? { aksi } : {},
        });
        Object.assign(rekam, data?.result || {});
    } catch {
        rekam.rute = [];
    } finally {
        rekam.memuat = false;
    }
}

async function ambilBerat(aksi = '') {
    berat.memuat = true;
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/queries', {
            params: { urut: beratUrut.value, limit: 25, ...(aksi ? { aksi } : {}) },
        });
        Object.assign(berat, data?.result || {});
    } catch {
        berat.baris = [];
    } finally {
        berat.memuat = false;
    }
}
/*
 * 5 detik, bukan 3. Diukur pada dev: satu sampel ~1,5 dtk, dan itu HAMPIR
 * SELURUHNYA latensi bolak-balik — `SELECT 1` polos saja 128 ms ke DB dev yang
 * remote. Lima kueri × ~150 ms. Pada basis data yang dekat angkanya sepersepuluh,
 * tetapi denyut yang lebih rapat daripada biaya satu sampel berarti pemantaunya
 * tak pernah selesai sebelum diminta lagi.
 */
const liveInterval = 5;
let liveTimer = null;

async function ambilLive() {
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/live');
        Object.assign(live, data?.result || {});
    } catch {
        live.aktif = false;
        live.alasan = 'Gagal menghubungi endpoint pemantauan.';
    }
}

function togglePantau(on) {
    if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
    if (on) { ambilLive(); liveTimer = setInterval(ambilLive, liveInterval * 1000); }
}

const hoursLabel = computed(() => {
    const h = hours.value;
    return h >= 24 ? `${Math.round(h / 24)} hari` : `${h} jam`;
});

const verdict = computed(() => {
    if (summary.provisioned === false) return { tone: 'neutral', icon: 'bi bi-dash-circle', title: 'Belum aktif', detail: 'Tabel diagnostik belum tersedia.' };
    const conn = summary.conn_problem || 0;
    const slow = summary.slow_query || 0;
    const blk = summary.blocking_events || 0;
    const err = summary.err_5xx || 0;
    if (conn === 0 && slow === 0 && err === 0 && blk === 0) {
        return { tone: 'good', icon: 'bi bi-check-circle-fill', title: 'Sistem sehat', detail: `Tidak ada request lambat/error dalam ${hoursLabel.value}.` };
    }
    if (conn >= slow && conn > 0) {
        return { tone: 'warn', icon: 'bi bi-plug-fill', title: 'Gejala dominan: GAGAL AMBIL KONEKSI DB', detail: `${conn} request menggantung tanpa sempat query — indikasi Login Timeout / koneksi tak tersedia, BUKAN lock. Cek AUTO_CLOSE, jalur VPC, IP publik SQL Server.` };
    }
    if (slow > 0 || blk > 0) {
        return { tone: 'bad', icon: 'bi bi-hourglass-split', title: 'Gejala dominan: QUERY LAMBAT / LOCK', detail: `${slow} query lambat${blk ? `, ${blk} kejadian blocking` : ''}. Lihat SQL terlambat & panel blocking untuk transaksi penyebab.` };
    }
    return { tone: 'warn', icon: 'bi bi-exclamation-triangle-fill', title: 'Ada error 5xx', detail: `${err} error 5xx terdeteksi. Telusuri tabel di bawah.` };
});

function fmt(n) { return (n === null || n === undefined) ? '—' : Number(n).toLocaleString('id-ID'); }
function fmtTime(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return v;
    return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function kelasTag(k) {
    switch (k) {
        case 'conn': return { type: 'warning', text: 'Koneksi', icon: 'bi bi-plug' };
        case 'query': return { type: 'danger', text: 'Query', icon: 'bi bi-hourglass' };
        case 'error': return { type: 'danger', text: '5xx', icon: 'bi bi-x-octagon' };
        default: return { type: 'info', text: 'Lain', icon: 'bi bi-dot' };
    }
}
function rowClass({ row }) {
    if (row.Kelas === 'conn') return 'sh-row-conn';
    if (row.Kelas === 'query') return 'sh-row-query';
    if (row.Http_Status >= 500) return 'sh-row-error';
    return '';
}

async function loadSummary() {
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/summary', { params: { hours: hours.value } });
        Object.assign(summary, data?.result || {});
    } catch { ElMessage.error('Gagal memuat ringkasan diagnostik'); }
}
async function loadRequests(page = 1) {
    requests.loading = true;
    requests.page = page;
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/requests', {
            params: { hours: hours.value, kelas: kelas.value, q: q.value || undefined, page, per_page: requests.perPage },
        });
        requests.items = data?.result || [];
        requests.total = data?.pagination?.total ?? requests.items.length;
    } catch { ElMessage.error('Gagal memuat daftar request'); }
    finally { requests.loading = false; }
}
async function loadDbHealth() {
    try {
        const { data } = await axios.get('/admin/activity/diagnostics/db-health', { params: { hours: Math.min(hours.value, 72) } });
        Object.assign(dbHealth, data?.result || {});
    } catch { /* silent */ }
}

async function reloadAll() {
    loading.value = true;
    await Promise.all([loadSummary(), loadRequests(1), loadDbHealth()]);
    loading.value = false;
}

function toggleAuto(on) {
    if (timer) { clearInterval(timer); timer = null; }
    if (on) timer = setInterval(reloadAll, 30000);
}

onMounted(() => { reloadAll(); ambilLive(); });
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    // Timer pantau-langsung TERPISAH; melewatkannya membuat halaman yang sudah
    // ditinggalkan tetap memukul DB tiap tiga detik.
    if (liveTimer) clearInterval(liveTimer);
});
</script>

<style scoped>
.sys-health { --r: 16px; display: flex; flex-direction: column; gap: 1rem; }

/* ── Pantau Langsung ─────────────────────────────────────────────────────
   Sengaja dibedakan dari kartu lain: isinya keadaan SAAT INI, bukan rekaman.
   Tepi kiri hijau saat menyala, kelabu saat gerbangnya tertutup. */
.sh-live { position: relative; padding: 1rem 1.1rem; border-radius: var(--r); background: #fff;
    border: 1px solid var(--hcis-slate-200); box-shadow: 0 6px 18px -12px rgba(2, 6, 23, .25); overflow: hidden; }
.sh-live::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: var(--hcis-emerald-500); }
.sh-live.is-off::before { background: var(--hcis-slate-300); }
.sh-live.is-off { background: var(--hcis-slate-50); }

.sh-live__head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.sh-live__title { font-weight: 700; font-size: .95rem; color: var(--hcis-slate-900); display: inline-flex; align-items: center; gap: .45rem; }
.sh-live__title i { color: var(--hcis-emerald-500); }
.sh-live.is-off .sh-live__title i { color: var(--hcis-slate-400); }
.sh-live__title em { font-style: normal; font-weight: 600; font-size: .78rem; color: var(--hcis-slate-400); }
.sh-live__act { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }

.sh-live__off { margin: .7rem 0 0; font-size: .84rem; color: var(--hcis-slate-600); display: flex; align-items: flex-start; gap: .5rem; }
.sh-live__off i { color: var(--hcis-slate-400); margin-top: 2px; }
.sh-live__off small { display: block; color: var(--hcis-slate-400); font-size: .76rem; margin-top: 2px; }

.sh-live__angka { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .6rem; margin-top: .85rem; }
.sh-live__angka > div { padding: .55rem .7rem; border-radius: 12px; background: var(--hcis-slate-50); border: 1px solid #eef2f7; }
.sh-live__angka strong { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1; color: var(--hcis-slate-900); }
.sh-live__angka span { display: block; font-size: .75rem; font-weight: 600; color: var(--hcis-slate-500); margin-top: 4px; }
/* Angka yang bukan nol pada dua kolom ini SELALU pantas dilihat: keduanya
   bentuk paling merusak, dan keduanya tak muncul di panel rekaman mana pun. */
.sh-live__angka > div.is-bad { background: var(--hcis-red-50); border-color: var(--hcis-red-200); }
.sh-live__angka > div.is-bad strong { color: var(--hcis-red-700); }

.sh-live__blokir { margin-top: .8rem; display: flex; flex-direction: column; gap: .4rem; }
.sh-live__baris { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; font-size: .82rem;
    padding: .5rem .7rem; border-radius: 10px; background: var(--hcis-amber-50); border: 1px solid var(--hcis-amber-200); }
.sh-live__baris i { color: var(--hcis-amber-700); }
.sh-live__korban { font-weight: 600; color: var(--hcis-slate-900); }
.sh-live__akar { font-weight: 700; color: var(--hcis-red-700); }

.sh-live__aman { margin: .8rem 0 0; font-size: .82rem; color: var(--hcis-slate-500); }

.sh-live__objek { margin-top: .8rem; display: flex; flex-wrap: wrap; gap: .35rem; }
.sh-live__objek span { font-size: .74rem; padding: .2rem .5rem; border-radius: 999px;
    background: var(--hcis-slate-100); color: var(--hcis-slate-700); border: 1px solid var(--hcis-slate-200); }
.sh-live__objek em { font-style: normal; color: var(--hcis-slate-400); }

.sh-live__sql { display: block; flex-basis: 100%; margin-top: .3rem; font-size: .72rem; color: #7c2d12;
    background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: .35rem .5rem;
    white-space: pre-wrap; word-break: break-word; }

.sh-live__tunggu { margin-top: .8rem; display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
.sh-live__tunggu-judul { font-size: .74rem; font-weight: 700; color: var(--hcis-red-700); margin-right: .2rem; }
.sh-live__tunggu span:not(.sh-live__tunggu-judul) { font-size: .74rem; padding: .2rem .5rem; border-radius: 999px;
    background: var(--hcis-red-50); color: var(--hcis-red-900); border: 1px solid var(--hcis-red-200); }
.sh-live__tunggu em { font-style: normal; color: var(--hcis-red-700); }

.sh-live__nganggur { margin-top: .8rem; }
.sh-live__nganggur-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; font-size: .78rem;
    padding: .45rem .6rem; border-radius: 10px; background: var(--hcis-amber-50); border: 1px solid var(--hcis-amber-200); margin-top: .3rem; }
.sh-live__nganggur-baris b { color: var(--hcis-slate-900); }
.sh-live__nganggur-baris span { color: var(--hcis-amber-800); }
.sh-live__nganggur-baris code { flex-basis: 100%; font-size: .72rem; color: #7c2d12; white-space: pre-wrap; word-break: break-word; }

.sh-berat::before { background: #f97316; }
.sh-berat .sh-live__title i { color: #f97316; }
.sh-berat__ket { margin: .6rem 0 0; font-size: .78rem; color: var(--hcis-slate-500); }
.sh-berat__tabel { margin-top: .7rem; font-size: .78rem; }
.sh-berat__kepala, .sh-berat__baris { display: grid; grid-template-columns: 84px 96px 78px 92px 1fr; gap: .5rem; align-items: start; }
.sh-berat__kepala { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    color: var(--hcis-slate-400); padding: 0 .4rem .3rem; border-bottom: 1px solid #eef2f7; }
.sh-berat__baris { padding: .45rem .4rem; border-bottom: 1px solid var(--hcis-slate-100); }
.sh-berat__baris:hover { background: var(--hcis-slate-50); }
.sh-berat__n { text-align: right; font-variant-numeric: tabular-nums; color: var(--hcis-slate-700); font-weight: 600; }
/* Baca per eksekusi di atas 5.000 hampir selalu berarti scan, bukan seek —
   dan itulah yang paling sering jadi biang CPU. */
.sh-berat__n.is-bad { color: var(--hcis-red-700); }
.sh-berat__sql { color: var(--hcis-slate-900); word-break: break-word; line-height: 1.45; }
.sh-berat__sql em { font-style: normal; font-weight: 700; color: var(--hcis-violet-600); }

.sh-rekam::before { background: var(--hcis-slate-500); }
.sh-rekam.is-on::before { background: var(--hcis-red-600); }
.sh-rekam .sh-live__title i { color: var(--hcis-slate-500); }
.sh-rekam.is-on .sh-live__title i { color: var(--hcis-red-600); }
.sh-rekam__rute { margin-top: .7rem; padding: .55rem .7rem; border-radius: 12px;
    background: var(--hcis-slate-50); border: 1px solid #eef2f7; }
.sh-rekam__kepala { display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; font-size: .82rem; }
.sh-rekam__kepala b { color: var(--hcis-slate-900); }
.sh-rekam__kepala span { color: var(--hcis-slate-500); }
.sh-rekam__sql { display: grid; grid-template-columns: 48px 76px 1fr; gap: .5rem; align-items: start;
    margin-top: .3rem; font-size: .74rem; }
.sh-rekam__n { text-align: right; font-weight: 700; color: var(--hcis-slate-700); font-variant-numeric: tabular-nums; }
/* Kueri yang sama berulang 5x atau lebih dalam satu rute hampir selalu N+1. */
.sh-rekam__n.is-bad { color: var(--hcis-red-700); }
.sh-rekam__ms { text-align: right; color: var(--hcis-slate-500); font-variant-numeric: tabular-nums; }
.sh-rekam__sql code { color: var(--hcis-slate-700); word-break: break-word; line-height: 1.4; }

@media (max-width: 900px) {
    .sh-berat__kepala { display: none; }
    .sh-berat__baris { grid-template-columns: 1fr 1fr; }
    .sh-berat__sql { grid-column: 1 / -1; }
}

.sh-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.sh-toolbar__left, .sh-toolbar__right { display: flex; align-items: center; gap: .6rem; }
.sh-title { font-weight: 700; font-size: 1.05rem; color: var(--hcis-slate-900); display: inline-flex; align-items: center; gap: .5rem; }
.sh-title i { color: var(--hcis-red-500); }

/* KPI cards */
.sh-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .85rem; }
.sh-kpi { position: relative; display: flex; align-items: center; gap: .85rem; padding: 1rem 1.1rem; border-radius: var(--r);
    background: #fff; border: 1px solid #eef2f7; box-shadow: 0 6px 18px -12px rgba(2, 6, 23, .25); overflow: hidden; }
.sh-kpi::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: var(--c, var(--hcis-slate-400)); }
.sh-kpi__ic { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem;
    color: var(--c, var(--hcis-slate-500)); background: color-mix(in srgb, var(--c, var(--hcis-slate-400)) 14%, #fff); flex: none; }
.sh-kpi strong { display: block; font-size: 1.5rem; font-weight: 800; line-height: 1; color: var(--hcis-slate-900); }
.sh-kpi strong em { font-size: .8rem; font-weight: 700; color: var(--hcis-slate-400); font-style: normal; margin-left: 2px; }
.sh-kpi span { display: block; font-size: .82rem; font-weight: 600; color: var(--hcis-slate-700); margin-top: 3px; }
.sh-kpi small { display: block; font-size: .72rem; color: var(--hcis-slate-400); }
.sh-kpi.is-slate { --c: var(--hcis-slate-500); } .sh-kpi.is-primary { --c: var(--hcis-blue-600); } .sh-kpi.is-danger { --c: var(--hcis-red-500); }
.sh-kpi.is-amber { --c: var(--hcis-amber-500); } .sh-kpi.is-violet { --c: var(--hcis-violet-600); } .sh-kpi.is-rose { --c: var(--hcis-rose-600); }
.sh-kpi.is-hot { animation: shPulse 2.2s ease-in-out infinite; }
@keyframes shPulse { 0%, 100% { box-shadow: 0 6px 18px -12px rgba(2, 6, 23, .25); } 50% { box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 22%, transparent); } }

/* Verdict */
.sh-verdict { display: flex; align-items: center; gap: .9rem; padding: .9rem 1.1rem; border-radius: var(--r);
    border: 1px solid; font-size: .9rem; }
.sh-verdict i { font-size: 1.5rem; flex: none; }
.sh-verdict strong { display: block; font-weight: 800; }
.sh-verdict span { display: block; opacity: .85; margin-top: 2px; }
.sh-verdict.good { background: var(--hcis-green-50); border-color: var(--hcis-green-200); color: #166534; }
.sh-verdict.warn { background: var(--hcis-amber-50); border-color: var(--hcis-amber-200); color: var(--hcis-amber-800); }
.sh-verdict.bad { background: var(--hcis-red-50); border-color: var(--hcis-red-200); color: var(--hcis-red-800); }
.sh-verdict.neutral { background: var(--hcis-slate-50); border-color: var(--hcis-slate-200); color: var(--hcis-slate-600); }

/* Panels */
.sh-panel { background: #fff; border: 1px solid #eef2f7; border-radius: var(--r); padding: 1rem 1.1rem;
    box-shadow: 0 6px 18px -14px rgba(2, 6, 23, .2); }
.sh-panel__head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; }
.sh-panel__head h5 { margin: 0; font-weight: 700; font-size: .98rem; color: var(--hcis-slate-900); display: inline-flex; align-items: center; gap: .5rem; }
.sh-filters { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }

.sh-table { width: 100%; }
.sh-table :deep(.sh-row-conn) { background: var(--hcis-amber-50); }
.sh-table :deep(.sh-row-query) { background: #fdf4ff; }
.sh-table :deep(.sh-row-error) { background: var(--hcis-red-50); }

.muted { color: var(--hcis-slate-400); }
.u { font-size: .68rem; color: var(--hcis-slate-300); font-style: normal; margin-left: 1px; }
.sh-qc { color: var(--hcis-slate-400); margin-left: 4px; }
.sh-hot { color: var(--hcis-red-600); font-weight: 800; }
.sh-method { font-size: .64rem; font-weight: 800; padding: 1px 5px; border-radius: 5px; margin-right: 5px; background: var(--hcis-indigo-50); color: var(--hcis-indigo-600); }
.sh-method[data-m="POST"], .sh-method[data-m="PUT"], .sh-method[data-m="PATCH"], .sh-method[data-m="DELETE"] { background: var(--hcis-amber-100); color: var(--hcis-amber-700); }
.sh-path { font-size: .78rem; color: var(--hcis-slate-700); }
.sh-sql { display: inline-block; max-width: 100%; font-size: .72rem; color: var(--hcis-violet-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }
.sh-wait { font-weight: 700; color: var(--hcis-red-700); font-size: .78rem; }
.sh-status { font-weight: 800; font-size: .82rem; }
.sh-status.is-5xx { color: var(--hcis-red-600); } .sh-status.is-4xx { color: var(--hcis-amber-600); } .sh-status.is-ok { color: var(--hcis-green-600); }
.sh-pager { display: flex; justify-content: flex-end; margin-top: .6rem; }

.sh-note { padding: .8rem 1rem; border-radius: 12px; background: var(--hcis-slate-50); color: var(--hcis-slate-600); font-size: .85rem; }
.sh-note.ok { background: var(--hcis-green-50); color: #166534; }
.sh-note code, .sh-empty code { background: var(--hcis-slate-900); color: var(--hcis-slate-200); padding: 2px 7px; border-radius: 6px; font-size: .76rem; }

.sh-empty { text-align: center; padding: 2.5rem 1rem; background: #fff; border: 1px dashed var(--hcis-slate-300); border-radius: var(--r); }
.sh-empty i { font-size: 2.4rem; color: var(--hcis-amber-500); }
.sh-empty h4 { margin: .6rem 0 .3rem; color: var(--hcis-slate-900); }
.sh-empty p { color: var(--hcis-slate-500); margin-bottom: .6rem; }
</style>
