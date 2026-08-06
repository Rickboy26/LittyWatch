<?php
declare(strict_types=1);

namespace LittyWatch\Services;

use LittyWatch\Repositories\WatchlistRepository;

final class WatchlistService
{
    public function __construct(private readonly WatchlistRepository $watchlist,private readonly AlertService $alerts) {}
    public function all(): array{return $this->watchlist->all();}
    public function marketOptions(string $query='',int $limit=250): array{return $this->watchlist->marketOptions($query,$limit);}
    public function save(string $key,?string $label,?float $buy,?float $sell): void
    {
        $key=trim($key);if($key==='')throw new \InvalidArgumentException('Kies een marktvariant.');
        if(!$this->watchlist->marketExists($key))throw new \InvalidArgumentException('Deze marktvariant bestaat niet in Market Intelligence.');
        foreach([$buy,$sell] as $target){if($target!==null&&$target<=0)throw new \InvalidArgumentException('Koersdoelen moeten groter zijn dan 0 ecto.');}
        $cleanLabel=$label!==null&&trim($label)!==''?trim($label):null;
        $this->watchlist->upsert($key,$cleanLabel,$buy,$sell);
        $this->alerts->syncWatchlistTargets($key,$cleanLabel??'',$buy,$sell);
    }
    public function remove(int $id): bool{$key=$this->watchlist->remove($id);if($key===null)return false;$this->alerts->removeWatchlistAlerts($key);return true;}
}
