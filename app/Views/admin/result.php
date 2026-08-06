<?php declare(strict_types=1); ?>
<section class="page-intro"><div><span class="kicker">SYSTEEMACTIE</span><h1><?= h($heading) ?></h1><p>De actie is uitgevoerd. Hieronder staat het technische resultaat.</p></div><a class="btn secondary" href="<?= h($back) ?>">Terug</a></section>
<section class="surface"><pre style="white-space:pre-wrap;overflow:auto"><?= h(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre></section>
