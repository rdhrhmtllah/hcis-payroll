{{-- Satu kartu permit (separuh A4). Dipakai cetak tunggal dan batch. --}}
    <table class="card-outer-frame" style="border-color: {{ $kartu['aksen'] }};">
        <tr>
            <td class="card-outer-cell">
                <table class="card-inner-frame">
                    <tr>
                        <td class="card-inner-cell">
                            <!-- ══════════════════════════════════════════════════════
                                 1. TOP HEADER ROW (BRAND & SHE BADGES)
                                 ══════════════════════════════════════════════════════ -->
                            <table class="kop-table">
                                <tr>
                                    <td>
                                        <div class="kop-brand">HCIS VEHICLE ACCESS PASS</div>
                                        <div class="kop-sub">{{ $kartu['sub_perusahaan'] }}</div>
                                    </td>
                                    <td class="kop-badge-cell">
                                        <span class="kop-notch-pill">OFFICIAL ACCESS</span>
                                        <div class="kop-shield-badge" style="background: {{ $kartu['aksen'] }};">
                                            <span class="kop-shield-text">SHE INSPECTION PASS</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══════════════════════════════════════════════════════
                                 2. MIDDLE ROW: ZONE BADGE (COL-3) & PLATE (COL-9)
                                 ══════════════════════════════════════════════════════ -->
                            <table class="middle-hero-table">
                                <tr>
                                    <!-- LEFT: ZONE BADGE (COL-3) - EXACT SAME HEIGHT -->
                                    <td class="zone-hero-cell" style="background: {{ $kartu['aksen'] }}; border-color: {{ $kartu['aksen'] }};">
                                        <div class="zone-badge-initial-box">{{ $kartu['zona_initial'] }}</div>
                                        <div class="zone-badge-title">{{ $kartu['kategori'] }}</div>
                                    </td>

                                    <!-- GAP -->
                                    <td class="hero-col-gap"></td>

                                    <!-- RIGHT: HERO OBSIDIAN LICENSE PLATE (COL-9) - EXACT SAME HEIGHT -->
                                    <td class="plate-hero-cell" style="border-color: {{ $kartu['aksen'] }};">
                                        <div class="plate-top-label">&bull; NOMOR REGISTRASI KENDARAAN &bull;</div>
                                        <div class="plate-number">{{ $kartu['plat'] }}</div>
                                        <div>
                                            <span class="plate-vehicle-tag">{{ $kartu['kendaraan'] }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- ══════════════════════════════════════════════════════
                                 3. BOTTOM ROW: MASA BERLAKU & FULL-SIZE QR CODE
                                 ══════════════════════════════════════════════════════ -->
                            <table class="validity-split-table">
                                <tr>
                                    <!-- LEFT: MASA BERLAKU HINGGA (EXPANDED TO MEET QR) -->
                                    <td class="validity-card-left" style="border-left-color: {{ $kartu['aksen'] }}; background: {{ $kartu['bg_aksen'] }};">
                                        <div class="validity-label">Masa Berlaku Hingga</div>
                                        <div class="validity-date" style="color: {{ $kartu['aksen'] }};">{{ $kartu['sampai'] }}</div>
                                        <div class="validity-sub">
                                            Berlaku Sejak: <strong>{{ $kartu['sejak'] }}</strong> &bull; {{ $kartu['tipe_kendaraan'] }}
                                        </div>
                                        <div class="validity-meta-tag">
                                            NO. PERMIT: {{ $kartu['no_transaksi'] }} &bull; SERI: {{ $kartu['seri'] }}
                                        </div>
                                    </td>

                                    <!-- GAP -->
                                    <td class="validity-card-gap"></td>

                                    <!-- RIGHT: DEDICATED FULL-SIZE QR CODE (FLUSH RIGHT) -->
                                    <td class="validity-card-qr">
                                        @if(!empty($kartu['qr']))
                                            <img src="{{ $kartu['qr'] }}" width="136" height="136" style="display: block; width: 136px; height: 136px; margin: 0 0 0 auto;">
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
