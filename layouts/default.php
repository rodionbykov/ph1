<?php

$title = $rc['title'] ?? '';
$startTime = (int) ($rc['startTime'] ?? 0);
$duration = (int) floor(microtime(true) * 1000) - $startTime;
$body ??= '';
$framework ??= [];
?>
<html>
    <head>
        <title>FW/1 Skeleton - <?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    </head>
    <body>
        <h1>FW/1 Default Layout</h1>
        <?= $body ?>
        <p style="font-size: small;">
            Powered by FW/1 version <?= htmlspecialchars($framework['version'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.<br>
            This request took <?= $duration ?>ms.
        </p>
    </body>
</html>