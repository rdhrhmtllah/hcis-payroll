import { installFetchCsrfGuard } from './utils/csrf';
import './shell-topbar';
import './shell-sidebar';

// shell.js bisa dimuat di layout legacy (footer.blade.php) yang TIDAK memuat
// bootstrap.js, jadi pasang guard di sini juga. installFetchCsrfGuard idempoten
// (cek __csrfFetchGuardInstalled), aman bila app.js sudah memasangnya.
installFetchCsrfGuard();
