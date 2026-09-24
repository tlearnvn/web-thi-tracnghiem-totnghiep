<?php
use App\Core\Session;
use App\Core\View;

$flash = Session::takeFlash();
?>
<script>window.TN_FLASH = <?= js_json($flash) ?>;</script>
<script src="<?= asset('js/icons.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= View::stack('scripts') ?>
