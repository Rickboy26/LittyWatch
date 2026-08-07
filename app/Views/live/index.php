<?php declare(strict_types=1);?>
<section class="page-intro"><div><span class="kicker">REALTIME</span><h1>Live Kamadan feed</h1><p>Ruwe berichten met zichtbare parserstatus.</p></div><div class="actions"><a class="btn" href="/admin/collect">Nu ophalen</a></div></section>
<section class="surface"><div class="tablewrap"><table><thead><tr><th>Speler</th><th>Bericht</th><th>Parser</th><th>Moment</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="4" class="muted">Nog geen berichten.</td></tr><?php endif;?>
<?php foreach($rows as$r):$status=(string)($r['parser_status']??'review');?>
<tr><td><a class="itemlink" href="/trader?name=<?=rawurlencode((string)$r['player'])?>"><?=h($r['player'])?></a></td><td><?=h($r['message'])?></td><td><span class="parse-status <?=h($status)?>"><?=h($r['parser_summary']?:'Nog niet geanalyseerd')?></span></td><td class="muted"><?=h(lw_local_datetime($r['posted_at']))?></td></tr>
<?php endforeach;?></tbody></table></div></section>