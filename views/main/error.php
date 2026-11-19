<?php

$this->disableLayout();
$exception = $request['exception'] ?? null;
$failedAction = $request['failedAction'] ?? 'unknown';
?>
<div style="width: 50%; color: red; border: 2px dotted red; background-color: #f9f9f9; padding: 10px;">
    <h1 style="color: red;">ERROR!</h1>
    <div style="width: 100%; text-align: left;">
        <p><b>An error occurred!</b></p>
        <b>Action:</b> <?= htmlspecialchars((string) $failedAction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
        <b>Error:</b> <?= htmlspecialchars($exception?->getMessage() ?? 'unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
        <b>Type:</b> <?= htmlspecialchars($exception ? $exception::class : 'unknown', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
        <b>Details:</b> <?= htmlspecialchars($exception?->getTraceAsString() ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
    </div>
</div>