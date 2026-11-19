<?php

$rc['title'] ??= 'Default View';
?>
<p>This is the default view for FW/1.</p>
<p>This page was rendered on <?= htmlspecialchars((string) ($rc['today'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</p>