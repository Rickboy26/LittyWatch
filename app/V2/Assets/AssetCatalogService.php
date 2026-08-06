<?php

declare(strict_types=1);

namespace LittyWatch\V2\Assets;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class AssetCatalogService
{
    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    public function install(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS asset_imports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_key TEXT NOT NULL UNIQUE,
    source_name TEXT NOT NULL,
    extractor_version TEXT,
    gw_dat_bytes INTEGER,
    declared_icons INTEGER NOT NULL DEFAULT 0,
    imported_icons INTEGER NOT NULL DEFAULT 0,
    skipped_icons INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'completed',
    message TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS item_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    import_id INTEGER NOT NULL,
    dat_file_id INTEGER,
    source_filename TEXT NOT NULL,
    relative_path TEXT NOT NULL,
    web_path TEXT NOT NULL,
    sha256 TEXT NOT NULL UNIQUE,
    bytes INTEGER,
    width INTEGER,
    height INTEGER,
    source_model_id INTEGER,
    source_name TEXT,
    source_type TEXT,
    source_rarity TEXT,
    linked_item_key TEXT,
    linked_item_name TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(import_id) REFERENCES asset_imports(id) ON DELETE CASCADE
)
SQL);
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_assets_dat_file ON item_assets(dat_file_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_assets_link ON item_assets(linked_item_key)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_assets_name ON item_assets(linked_item_name)');
    }

    /** @return array<string,mixed> */
    public function importZip(string $zipPath, ?string $displayName = null): array
    {
        $this->install();
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('De PHP-extensie zip/ZipArchive ontbreekt op de server.');
        if (!is_file($zipPath) || !is_readable($zipPath)) throw new RuntimeException('Het assetpakket kon niet worden gelezen.');
        if ((int)filesize($zipPath) > 512 * 1024 * 1024) throw new RuntimeException('Het assetpakket is groter dan 512 MB.');

        $batchKey = hash_file('sha256', $zipPath);
        $existing = $this->pdo->prepare('SELECT * FROM asset_imports WHERE batch_key = :batch LIMIT 1');
        $existing->execute([':batch' => $batchKey]);
        if ($row = $existing->fetch()) return ['ok'=>true,'duplicate'=>true,'message'=>'Dit pakket is al geïmporteerd.','import'=>$row];

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) throw new RuntimeException('Het ZIP-bestand kon niet worden geopend (code '.$opened.').');

        try {
            $entries = $this->zipEntries($zip);
            $manifestEntry = $entries['manifest.json'] ?? null;
            if ($manifestEntry === null) throw new RuntimeException('manifest.json ontbreekt in het assetpakket.');
            $raw = $zip->getFromIndex($manifestEntry['index']);
            if (!is_string($raw)) throw new RuntimeException('manifest.json kon niet worden gelezen.');
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 'littywatch-gw1-assets') throw new RuntimeException('Dit is geen geldig LittyWatch GW1-assetpakket.');
            $items = $manifest['items'] ?? null;
            if (!is_array($items)) throw new RuntimeException('De itemlijst ontbreekt in manifest.json.');
            if (count($items) > 15000) throw new RuntimeException('Het manifest bevat onverwacht veel assets.');

            $folder = substr($batchKey, 0, 16);
            $relativeBase = 'assets/game-items/'.$folder;
            $absoluteBase = $this->root.'/'.$relativeBase;
            if (!is_dir($absoluteBase) && !mkdir($absoluteBase, 0775, true) && !is_dir($absoluteBase)) throw new RuntimeException('assets/game-items is niet schrijfbaar.');

            $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(<<<'SQL'
INSERT INTO asset_imports (batch_key,source_name,extractor_version,gw_dat_bytes,declared_icons,imported_icons,skipped_icons,status)
VALUES (:batch,:source,:version,:bytes,:declared,0,0,'processing')
SQL);
            $stmt->execute([
                ':batch'=>$batchKey, ':source'=>$displayName ?: basename($zipPath),
                ':version'=>$this->nullableString($manifest['extractor_version'] ?? null),
                ':bytes'=>isset($source['gw_dat_bytes']) ? (int)$source['gw_dat_bytes'] : null,
                ':declared'=>count($items),
            ]);
            $importId = (int)$this->pdo->lastInsertId();
            $insert = $this->pdo->prepare(<<<'SQL'
INSERT OR IGNORE INTO item_assets (
 import_id,dat_file_id,source_filename,relative_path,web_path,sha256,bytes,width,height,
 source_model_id,source_name,source_type,source_rarity,linked_item_key,linked_item_name,created_at,updated_at
) VALUES (
 :import_id,:dat_file_id,:source_filename,:relative_path,:web_path,:sha256,:bytes,:width,:height,
 :source_model_id,:source_name,:source_type,:source_rarity,:linked_item_key,:linked_item_name,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP
)
SQL);
            $imported=0; $skipped=0;
            foreach ($items as $item) {
                if (!is_array($item)) { $skipped++; continue; }
                $manifestPath = $this->normalizeZipPath((string)($item['file'] ?? ''));
                if ($manifestPath === '' || !str_starts_with($manifestPath,'icons/')) { $skipped++; continue; }
                $entry = $entries[$manifestPath] ?? null;
                if ($entry === null) { $skipped++; continue; }
                $ext = strtolower(pathinfo($manifestPath, PATHINFO_EXTENSION));
                if (!in_array($ext,['png','jpg','jpeg','webp','gif'],true)) { $skipped++; continue; }
                $contents = $zip->getFromIndex($entry['index']);
                if (!is_string($contents) || $contents === '' || strlen($contents) > 5*1024*1024) { $skipped++; continue; }
                $hash = hash('sha256',$contents);
                $declaredHash = strtolower(trim((string)($item['sha256'] ?? '')));
                if ($declaredHash !== '' && !hash_equals($declaredHash,$hash)) { $skipped++; continue; }
                $sourceFilename = basename((string)($item['source_filename'] ?? basename($manifestPath)));
                $safe = preg_replace('/[^a-zA-Z0-9._-]+/','-',$sourceFilename) ?: $hash.'.'.$ext;
                if (!str_ends_with(strtolower($safe),'.'.$ext)) $safe .= '.'.$ext;
                $target = $absoluteBase.'/'.$safe;
                $relative = $relativeBase.'/'.$safe;
                if (is_file($target) && hash_file('sha256',$target) !== $hash) {
                    $safe = pathinfo($safe,PATHINFO_FILENAME).'-'.substr($hash,0,8).'.'.$ext;
                    $target=$absoluteBase.'/'.$safe; $relative=$relativeBase.'/'.$safe;
                }
                if (!is_file($target) && file_put_contents($target,$contents,LOCK_EX) === false) throw new RuntimeException('Icoon kon niet worden opgeslagen: '.$safe);
                $datFileId = preg_match('/itemIcon_(\d+)/i',$sourceFilename,$m) ? (int)$m[1] : null;
                $sourceName = $this->nullableString($item['name'] ?? null);
                $linkedKey=null; $linkedName=null;
                if ($sourceName !== null && ($market=$this->findMarketItemByName($sourceName)) !== null) {
                    $linkedKey=(string)$market['item_key']; $linkedName=(string)$market['item'];
                }
                $insert->execute([
                    ':import_id'=>$importId, ':dat_file_id'=>$datFileId, ':source_filename'=>$sourceFilename,
                    ':relative_path'=>$relative, ':web_path'=>'/'.$relative, ':sha256'=>$hash,
                    ':bytes'=>isset($item['bytes'])?(int)$item['bytes']:strlen($contents),
                    ':width'=>isset($item['width'])?(int)$item['width']:null,
                    ':height'=>isset($item['height'])?(int)$item['height']:null,
                    ':source_model_id'=>isset($item['model_id'])?(int)$item['model_id']:null,
                    ':source_name'=>$sourceName, ':source_type'=>$this->nullableString($item['type'] ?? null),
                    ':source_rarity'=>$this->nullableString($item['rarity'] ?? null),
                    ':linked_item_key'=>$linkedKey, ':linked_item_name'=>$linkedName,
                ]);
                if ($insert->rowCount()>0) $imported++; else $skipped++;
            }
            $finish=$this->pdo->prepare("UPDATE asset_imports SET imported_icons=:i,skipped_icons=:s,status='completed',message=:m WHERE id=:id");
            $finish->execute([':i'=>$imported,':s'=>$skipped,':m'=>$imported.' iconen geïmporteerd; '.$skipped.' overgeslagen.',':id'=>$importId]);
            $this->pdo->commit();
            return ['ok'=>true,'duplicate'=>false,'import_id'=>$importId,'declared'=>count($items),'imported'=>$imported,'skipped'=>$skipped,'batch_folder'=>$folder];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } finally { $zip->close(); }
    }

    public function link(int $assetId,string $itemKey): void
    {
        $this->install();
        $item=$this->findMarketItemByKey($itemKey);
        if ($item===null) throw new RuntimeException('Het gekozen marktitem bestaat niet.');
        $stmt=$this->pdo->prepare("UPDATE item_assets SET linked_item_key=:k,linked_item_name=:n,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute([':k'=>$item['item_key'],':n'=>$item['item'],':id'=>$assetId]);
        if ($stmt->rowCount()===0) throw new RuntimeException('Het icoon bestaat niet of was al identiek gekoppeld.');
    }

    public function unlink(int $assetId): void
    {
        $stmt=$this->pdo->prepare("UPDATE item_assets SET linked_item_key=NULL,linked_item_name=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute([':id'=>$assetId]);
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $this->install();
        return [
            'imports'=>(int)$this->pdo->query('SELECT COUNT(*) FROM asset_imports')->fetchColumn(),
            'assets'=>(int)$this->pdo->query('SELECT COUNT(*) FROM item_assets')->fetchColumn(),
            'linked'=>(int)$this->pdo->query("SELECT COUNT(*) FROM item_assets WHERE linked_item_key IS NOT NULL AND linked_item_key<>''")->fetchColumn(),
            'unlinked'=>(int)$this->pdo->query("SELECT COUNT(*) FROM item_assets WHERE linked_item_key IS NULL OR linked_item_key=''")->fetchColumn(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function imports(): array { $this->install(); return $this->pdo->query('SELECT * FROM asset_imports ORDER BY id DESC LIMIT 20')->fetchAll(); }

    /** @return array<int,array<string,mixed>> */
    public function assets(string $query='',string $filter='all',int $limit=120,int $offset=0): array
    {
        $this->install(); $where=[]; $params=[];
        if ($filter==='linked') $where[]="linked_item_key IS NOT NULL AND linked_item_key<>''";
        elseif ($filter==='unlinked') $where[]="(linked_item_key IS NULL OR linked_item_key='')";
        if ($query!=='') { $where[]='(source_filename LIKE :q OR CAST(dat_file_id AS TEXT) LIKE :q OR linked_item_name LIKE :q OR linked_item_key LIKE :q)'; $params[':q']='%'.$query.'%'; }
        $sql='SELECT * FROM item_assets'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY CASE WHEN linked_item_key IS NULL OR linked_item_key=\'\' THEN 0 ELSE 1 END,dat_file_id,id LIMIT :limit OFFSET :offset';
        $stmt=$this->pdo->prepare($sql); foreach($params as $k=>$v)$stmt->bindValue($k,$v,PDO::PARAM_STR);
        $stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT); $stmt->bindValue(':offset',max(0,$offset),PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll();
    }

    /** @return array<int,array<string,string>> */
    public function marketItems(string $query='',int $limit=750): array
    {
        if (!$this->tableExists('structured_offers')) return [];
        $where="TRIM(COALESCE(item,''))<>''"; $params=[];
        if ($query!=='') { $where.=' AND (item LIKE :q OR item_key LIKE :q)'; $params[':q']='%'.$query.'%'; }
        $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,item FROM structured_offers WHERE $where GROUP BY item ORDER BY item COLLATE NOCASE LIMIT :limit");
        foreach($params as $k=>$v)$stmt->bindValue($k,$v,PDO::PARAM_STR); $stmt->bindValue(':limit',max(1,min(3000,$limit)),PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll();
    }

    /** @return array<int,string> */
    public function serverPackages(): array
    {
        $folder=$this->root.'/imports/assets'; if(!is_dir($folder))return[]; $files=glob($folder.'/*.zip')?:[];
        usort($files,static fn(string $a,string $b):int=>filemtime($b)<=>filemtime($a)); return array_map('basename',$files);
    }

    public function serverPackagePath(string $filename): string
    {
        $safe=basename($filename); if($safe!==$filename||!str_ends_with(strtolower($safe),'.zip'))throw new RuntimeException('Ongeldige pakketnaam.');
        $path=$this->root.'/imports/assets/'.$safe; if(!is_file($path))throw new RuntimeException('Het gekozen serverpakket bestaat niet.'); return $path;
    }

    /** @return array<string,array{index:int,name:string}> */
    private function zipEntries(ZipArchive $zip): array
    {
        $entries=[]; for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i); if(!is_string($name))continue; $n=$this->normalizeZipPath($name); if($n!==''&&!str_contains($n,'../'))$entries[$n]=['index'=>$i,'name'=>$name]; } return $entries;
    }
    private function normalizeZipPath(string $path): string { $path=str_replace('\\','/',trim($path)); $path=ltrim($path,'/'); while(str_contains($path,'//'))$path=str_replace('//','/',$path); return $path; }
    /** @return array<string,mixed>|null */
    private function findMarketItemByName(string $name): ?array
    {
        if(!$this->tableExists('structured_offers'))return null; $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,item FROM structured_offers WHERE LOWER(TRIM(item))=LOWER(TRIM(:n)) GROUP BY item LIMIT 1"); $stmt->execute([':n'=>$name]); $row=$stmt->fetch(); return $row?:null;
    }
    /** @return array<string,mixed>|null */
    private function findMarketItemByKey(string $key): ?array
    {
        if(!$this->tableExists('structured_offers'))return null; $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,MIN(item) item FROM structured_offers WHERE item_key=:k GROUP BY item_key LIMIT 1"); $stmt->execute([':k'=>trim($key)]); $row=$stmt->fetch(); return $row?:null;
    }
    private function tableExists(string $table): bool { $stmt=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:n LIMIT 1"); $stmt->execute([':n'=>$table]); return (bool)$stmt->fetchColumn(); }
    private function nullableString(mixed $value): ?string { $v=trim((string)($value??'')); return $v!==''?$v:null; }
}
