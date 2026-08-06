<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Repositories\AlertRepository;

final class AlertService
{
    public function __construct(private readonly AlertRepository $alerts) {$this->alerts->install();}
    public function all(): array{return $this->alerts->all();}
    public function events(int $limit=100,bool $unreadOnly=false): array{return $this->alerts->events($limit,$unreadOnly);}
    public function unreadCount(): int{return $this->alerts->unreadCount();}
    public function toggle(int $id): void{$this->alerts->toggle($id);}
    public function delete(int $id): void{$this->alerts->delete($id);}
    public function markRead(int $id): void{$this->alerts->markRead($id);}
    public function markAllRead(): void{$this->alerts->markAllRead();}
    public function removeWatchlistAlerts(string $key): void{$this->alerts->removeWatchlistAlerts($key);}

    public function create(string $key,string $label,string $type,?float $threshold): int
    {
        $this->validate($key,$type,$threshold);
        return $this->alerts->create(trim($key),trim($label),$type,$threshold);
    }

    public function syncWatchlistTargets(string $key,string $label,?float $buy,?float $sell): void
    {
        $this->syncOne($key,$label,'wts_below',$buy);
        $this->syncOne($key,$label,'wtb_above',$sell);
    }

    /** @return array{checked:int,triggered:int,reset:int} */
    public function evaluate(): array
    {
        if(!$this->alerts->hasMarketTable())return ['checked'=>0,'triggered'=>0,'reset'=>0];
        $checked=$triggered=$reset=0;
        foreach($this->alerts->enabled() as $alert){$checked++;$market=$this->alerts->market((string)$alert['market_key']);if(!$market){$this->alerts->markChecked((int)$alert['id'],false,null);continue;}
            $match=$this->match($alert,$market);if($match===null){if((int)$alert['condition_met']===1)$reset++;$this->alerts->markChecked((int)$alert['id'],false,null);continue;}
            [$type,$value,$message,$signature]=$match;$isNew=(int)$alert['condition_met']!==1||($signature!==''&&$signature!==(string)($alert['last_signature']??''));
            if($isNew){$this->alerts->insertEvent((int)$alert['id'],(string)$alert['market_key'],$type,$value,$message);$this->alerts->markTriggered((int)$alert['id']);$triggered++;}
            $this->alerts->markChecked((int)$alert['id'],true,$signature);
        }
        return ['checked'=>$checked,'triggered'=>$triggered,'reset'=>$reset];
    }

    private function syncOne(string $key,string $label,string $type,?float $threshold): void
    {
        $id=$this->alerts->findWatchlistAlert($key,$type);
        if($threshold===null){if($id!==null)$this->alerts->delete($id);return;}
        $this->validate($key,$type,$threshold);$label=trim($label)!==''?trim($label):$key;
        if($id===null){$this->alerts->create($key,$label,$type,$threshold,'watchlist');return;}
        $this->alerts->updateGenerated($id,$label,$threshold);
    }

    private function validate(string $key,string $type,?float $threshold): void
    {
        if(trim($key)==='')throw new \InvalidArgumentException('Kies een marktvariant.');
        if(!in_array($type,['wts_below','wtb_above','spread_above','new_offer'],true))throw new \InvalidArgumentException('Ongeldig alerttype.');
        if($type!=='new_offer'&&($threshold===null||$threshold<=0))throw new \InvalidArgumentException('Voer een geldige ectodrempel in.');
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $m @return array{0:string,1:float,2:string,3:string}|null */
    private function match(array $a,array $m): ?array
    {
        $type=(string)$a['condition_type'];$threshold=$a['threshold_ecto']!==null?(float)$a['threshold_ecto']:null;$item=(string)($m['item']??$a['market_key']);
        if($type==='wts_below'&&$threshold!==null){$v=(float)($m['best_wts_ecto']??0);if($v>0&&$v<=$threshold)return [$type,$v,"{$item}: goedkoopste WTS is {$v}e (doel maximaal {$threshold}e).",'wts:'.$v];}
        if($type==='wtb_above'&&$threshold!==null){$v=(float)($m['best_wtb_ecto']??0);if($v>0&&$v>=$threshold)return [$type,$v,"{$item}: hoogste WTB is {$v}e (doel minimaal {$threshold}e).",'wtb:'.$v];}
        if($type==='spread_above'&&$threshold!==null){$wtb=(float)($m['best_wtb_ecto']??0);$wts=(float)($m['best_wts_ecto']??0);$v=$wtb-$wts;if($wtb>0&&$wts>0&&$v>=$threshold)return [$type,$v,"{$item}: spread {$v}e (WTB {$wtb}e / WTS {$wts}e).",'spread:'.$wtb.':'.$wts];}
        if($type==='new_offer'){$last=(string)($m['last_activity']??'');if($last!==''&&strtotime($last)>=time()-1800)return [$type,0.0,"{$item}: nieuwe marktactiviteit om {$last}.",'activity:'.$last];}
        return null;
    }
}
