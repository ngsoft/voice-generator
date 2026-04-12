<?php
script(asset('assets/vendor/redocly/redoc.standalone.js', true));
style("https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700");
title(trim(sprintf(
    '%s - %s',
    $swagger_data['spec']['info']['title'] ?? '',
    $swagger_data['spec']['info']['description'] ?? ''),
    '- '));
extend('site', ['display_nav' => true]); ?>
<div id="swagger-ui"></div>


<script id="swagger-data"
        type="application/json"><?= isset($swagger_data) ? json_encode($swagger_data, 65) : '{"spec":{}}' ?></script>
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

