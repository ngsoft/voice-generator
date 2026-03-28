<?php extend('layout');
vite('app/app.ts');
echo $content ?? "";
?>
<script class="hidden"
        type="application/json"
        id="app-data"><?= isset($vite_data) ? json_encode($vite_data, 65) : '{}' ?></script>


