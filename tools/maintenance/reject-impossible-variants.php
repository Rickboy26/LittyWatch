<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use LittyWatch\Market\VariantValidityGate;

$gate=new VariantValidityGate();$db=db();
$rows=$db->query("SELECT id,item_key,requirement,attribute_key,attribute_name,is_oldschool,is_inscribable FROM structured_offers WHERE quality_status='accepted'")->fetchAll();
$upd=$db->prepare("UPDATE structured_offers SET quality_status='rejected',quality_reason=?,lifecycle_status='rejected',lifecycle_updated_at=? WHERE id=?");
$n=0;$db->beginTransaction();
try{foreach($rows as $row){$c=$gate->inspect($row);if($c['allowed'])continue;$upd->execute([$c['reason'],date(DATE_ATOM),$row['id']]);$n+=$upd->rowCount();}$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
echo "Onmogelijke varianten rejected: {$n}\n";
