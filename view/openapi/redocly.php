<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($base)) : ?>
        <base href="<?= $base ?>/">
    <?php endif; ?>
    <title><?= trim(sprintf('%s - %s', $swagger_data['spec']['info']['title'] ?? '', $swagger_data['spec']['info']['description'] ?? ''), '- ') ?></title>
    <link rel="shortcut icon" href="<?= asset("favicon.ico") ?>">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
    <script id="swagger-data"
            type="application/json"><?= isset($swagger_data) ? json_encode($swagger_data, 65) : '{"spec":{}}' ?></script>

</head>
<body>
<div id="swagger-ui"></div>
<script crossorigin src="<?= asset('assets/vendor/redocly/redoc.standalone.js', true) ?>"></script>
<?= ($scripts_block ?? '') ?>
<script type="text/javascript">
    (() => {
        function loadRedocly(userOptions = {}) {
            const
                /** @type HTMLDivElement */  script = document.getElementById('swagger-data'),
                /** @type any */     data = JSON.parse(script.innerText);
            Redoc.init(data.spec, {...(data.config ?? {}), ...userOptions}, document.getElementById('swagger-ui'));
        }

        window.onload = () => {
            loadRedocly();
        };
    })();
</script>
</body>
</html>

