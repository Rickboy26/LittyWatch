<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use PDO;

final class AlertRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function install(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS alerts (id INTEGER PRIMARY KEY AUTOINCREMENT,market_key TEXT NOT NULL,label TEXT NOT NULL DEFAULT '',condition_type TEXT NOT NULL,threshold_ecto REAL,source TEXT NOT NULL DEFAULT 'manual',is_enabled INTEGER NOT NULL DEFAULT 1,condition_met INTEGER NOT NULL DEFAULT 0,last_signature TEXT,last_checked_at TEXT,last_triggered_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS alert_events (id INTEGER PRIMARY KEY AUTOINCREMENT,alert_id INTEGER NOT NULL,market_key TEXT NOT NULL,event_type TEXT NOT NULL,observed_value_ecto REAL,message TEXT NOT NULL,is_read INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(alert_id) REFERENCES alerts(id) ON DELETE CASCADE)");
        foreach([['alerts','source',"TEXT NOT NULL DEFAULT 'manual'"],['alerts','condition_met','INTEGER NOT NULL DEFAULT 0'],['alerts','last_signature','TEXT'],['alerts','last_checked_at','TEXT'],['alerts','updated_at',"TEXT NOT NULL DEFAULT ''"],['alert_events','is_read','INTEGER NOT NULL DEFAULT 0']] as [$table,$column,$definition]){$this->ensureColumn($table,$column,$definition);}
        $this->pdo->exec("UPDATE alerts SET updated_at=CURRENT_TIMESTAMP WHERE updated_at IS NULL OR updated_at=''");
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_market ON alerts(market_key)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_enabled ON alerts(is_enabled,condition_type)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_events_alert ON alert_events(alert_id,created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_alert_events_unread ON alert_events(is_read,created_at)');
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->install();
        return $this->pdo->query("SELECT a.*,mi.item,mi.best_wtb_ecto,mi.best_wts_ecto,mi.median_wtb_ecto,mi.median_wts_ecto,mi.last_activity,mi.deal_score,mi.confidence_score FROM alerts a LEFT JOIN market_intelligence mi ON mi.market_key=a.market_key ORDER BY a.is_enabled DESC,a.updated_at DESC,a.id DESC")->fetchAll();
    }

    public function create(string $key,string $label,string $type,?float $threshold,string $source='manual'): int
    {
        $stmt=$this->pdo->prepare("INSERT INTO alerts (market_key,label,condition_type,threshold_ecto,source,updated_at) VALUES (:key,:label,:type,:threshold,:source,CURRENT_TIMESTAMP)");
        $stmt->execute([':key'=>$key,':label'=>$label,':type'=>$type,':threshold'=>$threshold,':source'=>$source]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findWatchlistAlert(string $key,string $type): ?int
    {
        $stmt=$this->pdo->prepare("SELECT id FROM alerts WHERE market_key=:key AND condition_type=:type AND source='watchlist' LIMIT 1");
        $stmt->execute([':key'=>$key,':type'=>$type]);
        $id=$stmt->fetchColumn();
        return $id===false?null:(int)$id;
    }

    public function updateGenerated(int $id,string $label,float $threshold): void
    {
        $this->pdo->prepare("UPDATE alerts SET label=:label,threshold_ecto=:threshold,is_enabled=1,condition_met=0,last_signature=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':label'=>$label,':threshold'=>$threshold,':id'=>$id]);
    }

    public function removeWatchlistAlerts(string $key): void{$this->pdo->prepare("DELETE FROM alerts WHERE market_key=:key AND source='watchlist'")->execute([':key'=>$key]);}
    public function toggle(int $id): void{$this->pdo->prepare("UPDATE alerts SET is_enabled=CASE WHEN is_enabled=1 THEN 0 ELSE 1 END,condition_met=0,last_signature=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id'=>$id]);}
    public function delete(int $id): void{$this->pdo->prepare('DELETE FROM alert_events WHERE alert_id=:id')->execute([':id'=>$id]);$this->pdo->prepare('DELETE FROM alerts WHERE id=:id')->execute([':id'=>$id]);}

    /** @return array<int,array<string,mixed>> */
    public function enabled(): array{$this->install();return $this->pdo->query('SELECT * FROM alerts WHERE is_enabled=1 ORDER BY id')->fetchAll();}
    /** @return array<string,mixed>|null */
    public function market(string $key): ?array{$stmt=$this->pdo->prepare('SELECT * FROM market_intelligence WHERE market_key=:key LIMIT 1');$stmt->execute([':key'=>$key]);$row=$stmt->fetch();return $row?:null;}
    public function insertEvent(int $id,string $key,string $type,float $value,string $message): void{$this->pdo->prepare('INSERT INTO alert_events (alert_id,market_key,event_type,observed_value_ecto,message,is_read) VALUES (:id,:key,:type,:value,:message,0)')->execute([':id'=>$id,':key'=>$key,':type'=>$type,':value'=>$value,':message'=>$message]);}
    public function markTriggered(int $id): void{$this->pdo->prepare('UPDATE alerts SET last_triggered_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':id'=>$id]);}
    public function markChecked(int $id,bool $met,?string $signature): void{$this->pdo->prepare('UPDATE alerts SET condition_met=:met,last_signature=:signature,last_checked_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':met'=>$met?1:0,':signature'=>$signature,':id'=>$id]);}

    /** @return array<int,array<string,mixed>> */
    public function events(int $limit=100,bool $unreadOnly=false): array
    {
        $where=$unreadOnly?'WHERE e.is_read=0':'';
        $stmt=$this->pdo->prepare("SELECT e.*,a.label,a.condition_type,a.source FROM alert_events e JOIN alerts a ON a.id=e.alert_id {$where} ORDER BY e.id DESC LIMIT :limit");
        $stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT);$stmt->execute();return $stmt->fetchAll();
    }
    public function unreadCount(): int{return (int)$this->pdo->query('SELECT COUNT(*) FROM alert_events WHERE is_read=0')->fetchColumn();}
    public function markRead(int $id): void{$this->pdo->prepare('UPDATE alert_events SET is_read=1 WHERE id=:id')->execute([':id'=>$id]);}
    public function markAllRead(): void{$this->pdo->exec('UPDATE alert_events SET is_read=1 WHERE is_read=0');}
    public function hasMarketTable(): bool{$stmt=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name='market_intelligence'");$stmt->execute();return (bool)$stmt->fetchColumn();}

    private function ensureColumn(string $table,string $column,string $definition): void
    {
        foreach($this->pdo->query('PRAGMA table_info('.$table.')')->fetchAll() as $existing){if(($existing['name']??'')===$column)return;}
        $this->pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s',$table,$column,$definition));
    }
}
