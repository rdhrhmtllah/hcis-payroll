/**
 * Generator QR code — satu rumah.
 *
 * ── Kenapa dipusatkan ───────────────────────────────────────────────────────
 *
 * Generator ini pernah ada DUA kali: di userProfile.vue (ESS) dan di
 * Pages/VISITOR/Permit/VehiclePermit/components/qrHelper.js. Ketiga belas
 * fungsi pembantunya identik — encoder Reed-Solomon, evaluasi mask, penempatan
 * matriks — seluruhnya sekitar 315 baris.
 *
 * Salinan VISITOR TIDAK PERNAH DIIMPOR siapa pun: nol pemakai, terverifikasi
 * dengan mencari seluruh bentuk impornya di resources/js. Ia mati sejak commit
 * yang menambahkannya.
 *
 * ── Titik masuknya sudah menyimpang ─────────────────────────────────────────
 *
 * Pembantunya identik, tetapi generateQrCodeModules TIDAK. Masing-masing punya
 * sesuatu yang tak dimiliki yang lain:
 *
 *   salinan VISITOR (mati)  memotong teks di 76 karakter, quietZone = 2
 *   salinan ESS (hidup)     tanpa penjaga panjang,        quietZone = 4
 *
 * quietZone 4 yang benar: spesifikasi QR mensyaratkan zona sunyi empat modul,
 * dan 2 membuat sebagian pemindai kesulitan. Itu sebabnya bentuk ESS yang
 * dipertahankan di sini.
 *
 * Penjaga panjangnya juga tidak dipertahankan apa adanya, karena keduanya sama
 * salahnya. Diukur langsung pada kedua salinan:
 *
 *   VISITOR  77, 150, 400 karakter -> matriks SAMA seperti 76 (data dipotong diam-diam)
 *   ESS      77 -> matriks 544, 150 dan 400 -> sama-sama 543 (data meluber)
 *
 * Keduanya menghasilkan kode yang tidak memuat data yang diminta, tanpa
 * memberi tahu siapa pun. Fungsi di bawah menolak lebih dulu — lihat kapasitas().
 *
 * ── Catatan kejujuran tentang tingkat gawatnya ──────────────────────────────
 *
 * Pemanggil satu-satunya hari ini mengkodekan URL origin situs (sekitar 35
 * karakter), jadi luber itu TIDAK BISA terjadi sekarang. Ini cacat laten, bukan
 * kerusakan yang sedang berlangsung, dan disebut begitu supaya tidak terbaca
 * lebih dramatis daripada kenyataannya.
 */

const QR_ECC_CODEWORDS_LOW = {
    1: { data: 19, ecc: 7, alignment: [] },
    2: { data: 34, ecc: 10, alignment: [6, 18] },
    3: { data: 55, ecc: 15, alignment: [6, 22] },
    4: { data: 80, ecc: 20, alignment: [6, 26] },
};
const QR_FORMAT_MASK = 0x5412;
const QR_FORMAT_POLY = 0x537;
const QR_GF_EXP = [];
const QR_GF_LOG = [];

for (let i = 0, value = 1; i < 255; i++) {
    QR_GF_EXP[i] = value;
    QR_GF_LOG[value] = i;
    value <<= 1;
    if (value & 0x100) value ^= 0x11d;
}

for (let i = 255; i < 512; i++) {
    QR_GF_EXP[i] = QR_GF_EXP[i - 255];
}

function qrGfMultiply(left, right) {
    if (!left || !right) return 0;
    return QR_GF_EXP[QR_GF_LOG[left] + QR_GF_LOG[right]];
}

function qrGeneratorPolynomial(degree) {
    let polynomial = [1];

    for (let i = 0; i < degree; i++) {
        const next = new Array(polynomial.length + 1).fill(0);
        polynomial.forEach((coefficient, index) => {
            next[index] ^= coefficient;
            next[index + 1] ^= qrGfMultiply(coefficient, QR_GF_EXP[i]);
        });
        polynomial = next;
    }

    return polynomial;
}

function qrReedSolomonRemainder(data, degree) {
    const generator = qrGeneratorPolynomial(degree);
    const remainder = [...data, ...new Array(degree).fill(0)];

    data.forEach((_, index) => {
        const factor = remainder[index];
        if (!factor) return;

        generator.forEach((coefficient, generatorIndex) => {
            remainder[index + generatorIndex] ^= qrGfMultiply(coefficient, factor);
        });
    });

    return remainder.slice(data.length);
}

function qrSetModule(matrix, reserved, x, y, dark, isReserved = true) {
    if (x < 0 || y < 0 || y >= matrix.length || x >= matrix.length) return;
    matrix[y][x] = Boolean(dark);
    if (isReserved) reserved[y][x] = true;
}

function qrDrawFinder(matrix, reserved, x, y) {
    for (let yy = -1; yy <= 7; yy++) {
        for (let xx = -1; xx <= 7; xx++) {
            const distance = Math.max(Math.abs(xx - 3), Math.abs(yy - 3));
            qrSetModule(matrix, reserved, x + xx, y + yy, distance !== 2 && distance !== 4);
        }
    }
}

function qrDrawAlignment(matrix, reserved, centerX, centerY) {
    for (let y = -2; y <= 2; y++) {
        for (let x = -2; x <= 2; x++) {
            const distance = Math.max(Math.abs(x), Math.abs(y));
            qrSetModule(matrix, reserved, centerX + x, centerY + y, distance !== 1);
        }
    }
}

function qrCreateBaseMatrix(version) {
    const size = 21 + (version - 1) * 4;
    const matrix = Array.from({ length: size }, () => Array(size).fill(false));
    const reserved = Array.from({ length: size }, () => Array(size).fill(false));

    qrDrawFinder(matrix, reserved, 0, 0);
    qrDrawFinder(matrix, reserved, size - 7, 0);
    qrDrawFinder(matrix, reserved, 0, size - 7);

    for (let i = 0; i < size; i++) {
        qrSetModule(matrix, reserved, 6, i, i % 2 === 0);
        qrSetModule(matrix, reserved, i, 6, i % 2 === 0);
    }

    QR_ECC_CODEWORDS_LOW[version].alignment.forEach((y) => {
        QR_ECC_CODEWORDS_LOW[version].alignment.forEach((x) => {
            const overlapsFinder = (x === 6 && y === 6) || (x === 6 && y === size - 7) || (x === size - 7 && y === 6);
            if (!overlapsFinder) qrDrawAlignment(matrix, reserved, x, y);
        });
    });

    qrSetModule(matrix, reserved, 8, size - 8, true);

    for (let i = 0; i < 9; i++) {
        qrSetModule(matrix, reserved, 8, i, matrix[i][8]);
        qrSetModule(matrix, reserved, i, 8, matrix[8][i]);
        qrSetModule(matrix, reserved, size - 1 - i, 8, matrix[8][size - 1 - i]);
        qrSetModule(matrix, reserved, 8, size - 1 - i, matrix[size - 1 - i][8]);
    }

    return { matrix, reserved, size };
}

function qrMaskApplies(mask, x, y) {
    const sum = x + y;
    switch (mask) {
        case 0:
            return sum % 2 === 0;
        case 1:
            return y % 2 === 0;
        case 2:
            return x % 3 === 0;
        case 3:
            return sum % 3 === 0;
        case 4:
            return (Math.floor(y / 2) + Math.floor(x / 3)) % 2 === 0;
        case 5:
            return ((x * y) % 2) + ((x * y) % 3) === 0;
        case 6:
            return (((x * y) % 2) + ((x * y) % 3)) % 2 === 0;
        case 7:
            return (((x + y) % 2) + ((x * y) % 3)) % 2 === 0;
        default:
            return false;
    }
}

function qrFormatBits(mask) {
    let data = (1 << 3) | mask;
    let bits = data << 10;

    for (let i = 14; i >= 10; i--) {
        if ((bits >>> i) & 1) bits ^= QR_FORMAT_POLY << (i - 10);
    }

    return ((data << 10) | bits) ^ QR_FORMAT_MASK;
}

function qrDrawFormatBits(matrix, mask) {
    const size = matrix.length;
    const bits = qrFormatBits(mask);
    const bit = (index) => ((bits >>> index) & 1) === 1;

    for (let i = 0; i <= 5; i++) matrix[i][8] = bit(i);
    matrix[7][8] = bit(6);
    matrix[8][8] = bit(7);
    matrix[8][7] = bit(8);
    for (let i = 9; i < 15; i++) matrix[8][14 - i] = bit(i);

    for (let i = 0; i < 8; i++) matrix[8][size - 1 - i] = bit(i);
    for (let i = 8; i < 15; i++) matrix[size - 15 + i][8] = bit(i);
}

function qrPenalty(matrix) {
    const size = matrix.length;
    let penalty = 0;

    for (let y = 0; y < size; y++) {
        let runColor = matrix[y][0];
        let runLength = 1;
        for (let x = 1; x < size; x++) {
            if (matrix[y][x] === runColor) {
                runLength++;
            } else {
                if (runLength >= 5) penalty += runLength - 2;
                runColor = matrix[y][x];
                runLength = 1;
            }
        }
        if (runLength >= 5) penalty += runLength - 2;
    }

    for (let x = 0; x < size; x++) {
        let runColor = matrix[0][x];
        let runLength = 1;
        for (let y = 1; y < size; y++) {
            if (matrix[y][x] === runColor) {
                runLength++;
            } else {
                if (runLength >= 5) penalty += runLength - 2;
                runColor = matrix[y][x];
                runLength = 1;
            }
        }
        if (runLength >= 5) penalty += runLength - 2;
    }

    for (let y = 0; y < size - 1; y++) {
        for (let x = 0; x < size - 1; x++) {
            const color = matrix[y][x];
            if (matrix[y][x + 1] === color && matrix[y + 1][x] === color && matrix[y + 1][x + 1] === color) {
                penalty += 3;
            }
        }
    }

    let dark = 0;
    matrix.forEach((row) =>
        row.forEach((module) => {
            if (module) dark++;
        }),
    );
    penalty += Math.floor(Math.abs((dark * 20) / (size * size) - 10)) * 10;

    return penalty;
}

function qrBuildDataCodewords(text, version) {
    const bytes = new TextEncoder().encode(text);
    const capacity = QR_ECC_CODEWORDS_LOW[version].data;
    const bits = [0, 1, 0, 0];

    for (let i = 7; i >= 0; i--) bits.push((bytes.length >>> i) & 1);
    bytes.forEach((byte) => {
        for (let i = 7; i >= 0; i--) bits.push((byte >>> i) & 1);
    });

    const maxBits = capacity * 8;
    for (let i = 0; i < 4 && bits.length < maxBits; i++) bits.push(0);
    while (bits.length % 8 !== 0) bits.push(0);

    const codewords = [];
    for (let i = 0; i < bits.length; i += 8) {
        codewords.push(bits.slice(i, i + 8).reduce((value, bit) => (value << 1) | bit, 0));
    }

    for (let pad = 0xec; codewords.length < capacity; pad ^= 0xec ^ 0x11) {
        codewords.push(pad);
    }

    return codewords;
}

function qrPlaceData(matrix, reserved, codewords, mask) {
    const size = matrix.length;
    const bits = codewords.flatMap((byte) => Array.from({ length: 8 }, (_, index) => (byte >>> (7 - index)) & 1));
    let bitIndex = 0;
    let upward = true;

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right--;

        for (let vertical = 0; vertical < size; vertical++) {
            const y = upward ? size - 1 - vertical : vertical;

            for (let offset = 0; offset < 2; offset++) {
                const x = right - offset;
                if (reserved[y][x]) continue;

                const dark = bitIndex < bits.length ? Boolean(bits[bitIndex]) : false;
                matrix[y][x] = qrMaskApplies(mask, x, y) ? !dark : dark;
                bitIndex++;
            }
        }

        upward = !upward;
    }
}

/**
 * Kapasitas byte-mode ECC level L untuk versi tertinggi yang didukung tabel di
 * atas. Melebihinya berarti kode yang dihasilkan tidak memuat data yang diminta.
 */
export function kapasitasMaksimum() {
    const versi = Object.keys(QR_ECC_CODEWORDS_LOW).map(Number);
    const tertinggi = Math.max(...versi);

    /* dua byte dipakai penanda mode dan penunjuk panjang */
    return QR_ECC_CODEWORDS_LOW[tertinggi].data - 2;
}

/**
 * Matriks QR untuk sebuah teks, atau null bila teksnya melebihi kapasitas.
 *
 * NULL, bukan lemparan: pemanggilnya berada di dalam computed Vue, dan lemparan
 * di sana mematahkan seluruh komponen — pelajaran dari pemformat tanggal.
 * Pemanggil WAJIB menangani null; jangan diam-diam menghasilkan kode yang tak
 * memuat datanya, sebagaimana kedua salinan lama.
 */
export function generateQrCodeModules(text) {
    const teks = String(text ?? "");
    if (teks.length === 0 || teks.length > kapasitasMaksimum()) {
        return null;
    }

    const bytes = new TextEncoder().encode(text);
    const version =
        Number(
            Object.keys(QR_ECC_CODEWORDS_LOW).find(
                (candidate) => bytes.length + 2 <= QR_ECC_CODEWORDS_LOW[candidate].data,
            ),
        ) || 4;
    const data = qrBuildDataCodewords(text, version);
    const ecc = qrReedSolomonRemainder(data, QR_ECC_CODEWORDS_LOW[version].ecc);
    const codewords = [...data, ...ecc];
    let bestMatrix = null;
    let bestMask = 0;
    let bestPenalty = Infinity;

    for (let mask = 0; mask < 8; mask++) {
        const { matrix, reserved } = qrCreateBaseMatrix(version);
        qrPlaceData(matrix, reserved, codewords, mask);
        qrDrawFormatBits(matrix, mask);
        const penalty = qrPenalty(matrix);

        if (penalty < bestPenalty) {
            bestMatrix = matrix;
            bestMask = mask;
            bestPenalty = penalty;
        }
    }

    qrDrawFormatBits(bestMatrix, bestMask);

    const quietZone = 4;
    const modules = [];
    bestMatrix.forEach((row, y) => {
        row.forEach((dark, x) => {
            if (dark) modules.push({ x: x + quietZone, y: y + quietZone });
        });
    });

    return {
        modules,
        viewBox: `0 0 ${bestMatrix.length + quietZone * 2} ${bestMatrix.length + quietZone * 2}`,
    };
}
