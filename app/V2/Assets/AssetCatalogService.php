<?php

declare(strict_types=1);

namespace LittyWatch\V2\Assets;

use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

final class AssetCatalogService
{
    private bool $installed = false;

    public function __construct(private readonly PDO $pdo, private readonly string $root) {}

    public function install(): void
    {
        if ($this->installed) return;
        $this->installed = true;

        // Phase 3M9: this service is used on a busy SQLite database while the
        // continuous Kamadan collector may be writing. Older builds executed
        // CREATE INDEX + the legacy migration on every summary/list call, which
        // unnecessarily requested write locks. Existing M5+ databases already
        // have the complete schema, so keep normal page loads strictly read-only.
        if ($this->schemaReady()) return;

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
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS item_icon_links (
    item_key TEXT PRIMARY KEY,
    item_name TEXT NOT NULL,
    asset_id INTEGER NOT NULL,
    dat_file_id INTEGER,
    match_source TEXT NOT NULL DEFAULT 'manual',
    confidence REAL,
    source_title TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(asset_id) REFERENCES item_assets(id) ON DELETE CASCADE
)
SQL);
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_icon_links_asset ON item_icon_links(asset_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_icon_links_dat ON item_icon_links(dat_file_id)');
        // Migrate the older one-link-per-asset model into the item-centric link table.
        $this->pdo->exec("INSERT OR IGNORE INTO item_icon_links(item_key,item_name,asset_id,dat_file_id,match_source,confidence,created_at,updated_at) SELECT linked_item_key,COALESCE(NULLIF(linked_item_name,''),linked_item_key),id,dat_file_id,'legacy',1.0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM item_assets WHERE linked_item_key IS NOT NULL AND linked_item_key<>''");
    }

    private function schemaReady(): bool
    {
        foreach (['asset_imports','item_assets','item_icon_links'] as $table) {
            $stmt=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name LIMIT 1");
            $stmt->execute([':name'=>$table]);
            if (!$stmt->fetchColumn()) return false;
        }
        return true;
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
            if ($manifestEntry !== null) {
                $raw = $zip->getFromIndex($manifestEntry['index']);
                if (!is_string($raw)) throw new RuntimeException('manifest.json kon niet worden gelezen.');
                $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
                $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (($manifest['format'] ?? null) !== 'littywatch-gw1-assets') throw new RuntimeException('Dit is geen geldig LittyWatch GW1-assetpakket.');
                $items = $manifest['items'] ?? null;
                if (!is_array($items)) throw new RuntimeException('De itemlijst ontbreekt in manifest.json.');
            } else {
                // Phase 3M3: a plain ZIP containing item_icon_12345.png files is
                // also valid. Names can be linked later; the DAT file id is kept.
                $items = [];
                foreach ($entries as $path => $entry) {
                    $filename = basename($path);
                    if (!preg_match('/item[_-]?icon[_-]?(\d+)\.(png|jpe?g|webp|gif)$/i', $filename)) continue;
                    $items[] = ['file'=>$path,'source_filename'=>$filename];
                }
                if ($items === []) throw new RuntimeException('Geen manifest.json en geen item_icon_*.png bestanden gevonden.');
                $manifest = [
                    'format' => 'littywatch-gw1-assets-loose',
                    'extractor_version' => 'plain-icon-zip',
                    'source' => [],
                    'items' => $items,
                ];
            }
            if (count($items) > 20000) throw new RuntimeException('Het pakket bevat onverwacht veel assets.');

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
                if ($manifestPath === '' || str_contains($manifestPath,'../')) { $skipped++; continue; }
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
                $datFileId = preg_match('/item[_-]?icon[_-]?(\d+)/i',$sourceFilename,$m) ? (int)$m[1] : null;
                // Shared Guild Wars icons may have identical pixels but different DAT IDs.
                // Namespace the unique DB key so no DAT entry is lost.
                $dbHash=$hash.':'.($datFileId!==null?'dat-'.$datFileId:'file-'.hash('sha1',$manifestPath));
                $sourceName = $this->nullableString($item['name'] ?? null);
                $linkedKey=null; $linkedName=null;
                if ($sourceName !== null && ($market=$this->findMarketItemByName($sourceName)) !== null) {
                    $linkedKey=(string)$market['item_key']; $linkedName=(string)$market['item'];
                }
                $insert->execute([
                    ':import_id'=>$importId, ':dat_file_id'=>$datFileId, ':source_filename'=>$sourceFilename,
                    ':relative_path'=>$relative, ':web_path'=>'/'.$relative, ':sha256'=>$dbHash,
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
            $this->pdo->exec("INSERT OR IGNORE INTO item_icon_links(item_key,item_name,asset_id,dat_file_id,match_source,confidence,created_at,updated_at) SELECT linked_item_key,COALESCE(NULLIF(linked_item_name,''),linked_item_key),id,dat_file_id,'manifest',1.0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM item_assets WHERE linked_item_key IS NOT NULL AND linked_item_key<>''");
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
        $item=$this->findMarketItemByKey($itemKey) ?? $this->findMarketItemByName($itemKey);
        if ($item===null) throw new RuntimeException('Het gekozen marktitem bestaat niet. Zoek op de exacte itemnaam of market item key.');
        $asset=$this->assetById($assetId);
        if($asset===null) throw new RuntimeException('Het gekozen inventory icoon bestaat niet.');
        $this->upsertItemLink($item,$asset,'manual',1.0,null);
        // Keep the legacy columns useful for older pages/builds, but do not
        // require an asset to belong to only one item; shared GW icons exist.
        if(trim((string)($asset['linked_item_key']??''))===''){
            $stmt=$this->pdo->prepare("UPDATE item_assets SET linked_item_key=:k,linked_item_name=:n,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute([':k'=>$item['item_key'],':n'=>$item['item'],':id'=>$assetId]);
        }
    }

    public function unlink(int $assetId): void
    {
        $this->install();
        $this->pdo->prepare('DELETE FROM item_icon_links WHERE asset_id=:id')->execute([':id'=>$assetId]);
        $stmt=$this->pdo->prepare("UPDATE item_assets SET linked_item_key=NULL,linked_item_name=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute([':id'=>$assetId]);
    }

    /** @param array<int,array<string,mixed>> $matches @return array<string,int> */
    public function bulkAutoLink(array $matches): array
    {
        $this->install();
        $accepted=0;$skipped=0;$missing=0;
        $started=!$this->pdo->inTransaction();if($started)$this->pdo->beginTransaction();
        try{
            foreach(array_slice($matches,0,200) as$match){
                if(!is_array($match)){ $skipped++; continue; }
                $key=trim((string)($match['item_key']??''));$dat=(int)($match['dat_file_id']??0);
                $confidence=(float)($match['confidence']??0);
                $title=trim((string)($match['source_title']??''));
                // Server-side guardrail: automatic mappings must be high confidence.
                if($key===''||$dat<=0||$confidence<0.90){$skipped++;continue;}
                $item=$this->findMarketItemByKey($key);$asset=$this->assetByDatId($dat);
                if($item===null||$asset===null){$missing++;continue;}
                $this->upsertItemLink($item,$asset,'gww_visual_match',min(1.0,$confidence),$title!==''?$title:null);
                $accepted++;
            }
            if($started)$this->pdo->commit();
        }catch(Throwable$e){if($started&&$this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return ['accepted'=>$accepted,'skipped'=>$skipped,'missing'=>$missing];
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $this->install();
        $assets=(int)$this->pdo->query('SELECT COUNT(*) FROM item_assets')->fetchColumn();
        $usedAssets=(int)$this->pdo->query('SELECT COUNT(DISTINCT asset_id) FROM item_icon_links')->fetchColumn();
        $linkedItems=(int)$this->pdo->query('SELECT COUNT(*) FROM item_icon_links')->fetchColumn();
        $marketItems=count($this->marketItems('',3000));
        return [
            'imports'=>(int)$this->pdo->query('SELECT COUNT(*) FROM asset_imports')->fetchColumn(),
            'assets'=>$assets,
            'linked'=>$usedAssets,
            'unlinked'=>max(0,$assets-$usedAssets),
            'linked_items'=>$linkedItems,
            'market_items'=>$marketItems,
            'unlinked_items'=>max(0,$marketItems-$linkedItems),
            'files'=>$this->bundledIconCount(),
        ];
    }

    private function bundledIconCount(): int
    {
        $manifest=$this->root.'/assets/game-items/inventory-manifest.json';
        if(is_file($manifest)){
            $decoded=json_decode((string)file_get_contents($manifest),true);
            $count=(int)($decoded['counts']['icons']??0);
            if($count>0)return $count;
        }
        $files=glob($this->root.'/assets/game-items/inventory/itemIcon_*.png')?:[];
        return count($files);
    }

    /** @return array<int,array<string,mixed>> */
    public function imports(): array { $this->install(); return $this->pdo->query('SELECT * FROM asset_imports ORDER BY id DESC LIMIT 20')->fetchAll(); }

    /** @return array<int,array<string,mixed>> */
    public function assets(string $query='',string $filter='all',int $limit=120,int $offset=0): array
    {
        $this->install(); $where=[]; $params=[];
        if ($filter==='linked') $where[]="EXISTS(SELECT 1 FROM item_icon_links l WHERE l.asset_id=item_assets.id)";
        elseif ($filter==='unlinked') $where[]="NOT EXISTS(SELECT 1 FROM item_icon_links l WHERE l.asset_id=item_assets.id)";
        if ($query!=='') { $where[]='(source_filename LIKE :q OR CAST(dat_file_id AS TEXT) LIKE :q OR linked_item_name LIKE :q OR linked_item_key LIKE :q OR EXISTS(SELECT 1 FROM item_icon_links lq WHERE lq.asset_id=item_assets.id AND (lq.item_name LIKE :q OR lq.item_key LIKE :q)))'; $params[':q']='%'.$query.'%'; }
        $sql="SELECT item_assets.*,(SELECT COUNT(*) FROM item_icon_links lc WHERE lc.asset_id=item_assets.id) AS link_count,(SELECT GROUP_CONCAT(item_name, ' · ') FROM item_icon_links ln WHERE ln.asset_id=item_assets.id) AS link_names FROM item_assets".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY CASE WHEN EXISTS(SELECT 1 FROM item_icon_links lo WHERE lo.asset_id=item_assets.id) THEN 1 ELSE 0 END,dat_file_id,id LIMIT :limit OFFSET :offset";
        $stmt=$this->pdo->prepare($sql); foreach($params as $k=>$v)$stmt->bindValue($k,$v,PDO::PARAM_STR);
        $stmt->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT); $stmt->bindValue(':offset',max(0,$offset),PDO::PARAM_INT); $stmt->execute(); return $stmt->fetchAll();
    }

    /** @return array<int,array<string,string>> */
    public function marketItems(string $query='',int $limit=750): array
    {
        $max=max(1,min(3000,$limit));
        $byKey=[];
        if($this->tableExists('structured_offers')){
            $where="TRIM(COALESCE(item,''))<>''"; $params=[];
            if($query!=='') { $where.=' AND (item LIKE :q OR item_key LIKE :q)'; $params[':q']='%'.$query.'%'; }
            $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,item FROM structured_offers WHERE $where GROUP BY item ORDER BY item COLLATE NOCASE LIMIT :limit");
            foreach($params as $k=>$v)$stmt->bindValue($k,$v,PDO::PARAM_STR);
            $stmt->bindValue(':limit',$max,PDO::PARAM_INT);$stmt->execute();
            foreach($stmt->fetchAll() as$row){$key=trim((string)($row['item_key']??''));$name=trim((string)($row['item']??''));if($key!==''&&$name!=='')$byKey[$key]=['item_key'=>$key,'item'=>$name];}
        }
        foreach($this->catalogItems() as$item){
            $key=(string)$item['item_key'];$name=(string)$item['item'];
            if($query!==''&&stripos($key,$query)===false&&stripos($name,$query)===false)continue;
            $byKey[$key]??=$item;
        }
        foreach($this->knowledgeItems() as$item){
            $key=(string)$item['item_key'];$name=(string)$item['item'];
            if($query!==''&&stripos($key,$query)===false&&stripos($name,$query)===false)continue;
            $byKey[$key]??=$item;
        }
        $rows=array_values($byKey);usort($rows,static fn(array$a,array$b):int=>strcasecmp($a['item'],$b['item']));
        return array_slice($rows,0,$max);
    }

    /** @return array<int,array{item_key:string,item:string,wiki_title:string}> */
    public function unlinkedMarketItems(int $limit=3000): array
    {
        $this->install();
        $linked=[];foreach($this->pdo->query('SELECT item_key FROM item_icon_links')->fetchAll() as$row)$linked[(string)$row['item_key']]=true;
        $wikiMap=[];$config=$this->root.'/config/item-images.php';if(is_file($config)){ $loaded=require$config;if(is_array($loaded))$wikiMap=$loaded; }
        $metadata=[];
        if($this->tableExists('item_metadata')){
            foreach($this->pdo->query("SELECT item_key,wiki_title FROM item_metadata WHERE TRIM(COALESCE(wiki_title,''))<>''")->fetchAll() as$row)$metadata[(string)$row['item_key']]=(string)$row['wiki_title'];
        }
        $out=[];
        foreach($this->marketItems('',max(1,min(3000,$limit))) as$item){
            $key=(string)$item['item_key'];if(isset($linked[$key]))continue;$name=(string)$item['item'];
            $out[]=['item_key'=>$key,'item'=>$name,'wiki_title'=>trim((string)($metadata[$key]??$wikiMap[$name]??$name))];
        }
        return$out;
    }

    /**
     * Scan already-present inventory icons (item_icon_12345.png) and reconcile
     * them with existing item_assets rows. Existing name links are preserved.
     * This is ideal when the icon folder is deployed together with the website.
     *
     * @return array{scanned:int,new:int,updated:int,linked:int,unlinked:int,path:string}
     */
    public function scanLocalIcons(): array
    {
        $this->install();
        $base=$this->root.'/assets/game-items';
        if(!is_dir($base)&&!mkdir($base,0775,true)&&!is_dir($base))throw new RuntimeException('assets/game-items kon niet worden aangemaakt.');

        $batch='local-inventory-scan';
        $stmt=$this->pdo->prepare("INSERT INTO asset_imports (batch_key,source_name,extractor_version,declared_icons,imported_icons,skipped_icons,status,message) VALUES (:b,'Lokale inventory icons','phase3m4-scan',0,0,0,'processing','') ON CONFLICT(batch_key) DO UPDATE SET status='processing',message='',created_at=CURRENT_TIMESTAMP");
        $stmt->execute([':b'=>$batch]);
        $get=$this->pdo->prepare('SELECT id FROM asset_imports WHERE batch_key=:b LIMIT 1');$get->execute([':b'=>$batch]);$importId=(int)$get->fetchColumn();

        $files=[];
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as$file){
            if(!$file instanceof \SplFileInfo||!$file->isFile())continue;
            $name=$file->getFilename();
            if(!preg_match('/item[_-]?icon[_-]?(\d+)\.(png|jpe?g|webp|gif)$/i',$name,$m))continue;
            $files[]=['path'=>$file->getPathname(),'name'=>$name,'id'=>(int)$m[1]];
        }

        $byDat=$this->pdo->prepare('SELECT * FROM item_assets WHERE dat_file_id=:d ORDER BY CASE WHEN linked_item_key IS NULL OR linked_item_key=\'\' THEN 1 ELSE 0 END,id DESC LIMIT 1');
        $update=$this->pdo->prepare('UPDATE item_assets SET import_id=:import_id,dat_file_id=:dat_file_id,source_filename=:source_filename,relative_path=:relative_path,web_path=:web_path,sha256=:sha256,bytes=:bytes,width=:width,height=:height,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $insert=$this->pdo->prepare('INSERT OR IGNORE INTO item_assets (import_id,dat_file_id,source_filename,relative_path,web_path,sha256,bytes,width,height,created_at,updated_at) VALUES (:import_id,:dat_file_id,:source_filename,:relative_path,:web_path,:sha256,:bytes,:width,:height,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $new=0;$updated=0;
        $startedTransaction=!$this->pdo->inTransaction();
        if($startedTransaction)$this->pdo->beginTransaction();
        try{
        foreach($files as$f){
            $hash=hash_file('sha256',$f['path']);if(!is_string($hash)||$hash==='')continue;
            $dbHash=$hash.':dat-'.$f['id'];
            $relative=ltrim(str_replace('\\','/',substr($f['path'],strlen($this->root))),'/');
            $dimensions=@getimagesize($f['path']);$width=is_array($dimensions)?(int)($dimensions[0]??0):null;$height=is_array($dimensions)?(int)($dimensions[1]??0):null;
            $byDat->execute([':d'=>$f['id']]);$row=$byDat->fetch();
            $params=[':import_id'=>$importId,':dat_file_id'=>$f['id'],':source_filename'=>$f['name'],':relative_path'=>$relative,':web_path'=>'/'.$relative,':sha256'=>$dbHash,':bytes'=>(int)filesize($f['path']),':width'=>$width,':height'=>$height];
            if($row){
                $params[':id']=(int)$row['id'];
                try{$update->execute($params);$updated++;}catch(Throwable){/* preserve an existing legacy/duplicate storage row */}
            }else{
                $insert->execute($params);if($insert->rowCount()>0)$new++;
            }
        }
        $this->applyManualOverrides();
        if($startedTransaction)$this->pdo->commit();
        }catch(Throwable $e){
            if($startedTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
        $summary=$this->summary();
        $finish=$this->pdo->prepare("UPDATE asset_imports SET declared_icons=:d,imported_icons=:i,skipped_icons=0,status='completed',message=:m WHERE id=:id");
        $finish->execute([':d'=>count($files),':i'=>$new+$updated,':m'=>count($files).' inventory icons gevonden; '.$new.' nieuw, '.$updated.' bijgewerkt.',':id'=>$importId]);
        return ['scanned'=>count($files),'new'=>$new,'updated'=>$updated,'market_items'=>$summary['market_items']??0,'linked_items'=>$summary['linked_items']??0,'unlinked_items'=>$summary['unlinked_items']??0,'used_icon_files'=>$summary['linked'],'unused_icon_files'=>$summary['unlinked'],'path'=>$base];
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
        if($this->tableExists('structured_offers')){
            $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,item FROM structured_offers WHERE LOWER(TRIM(item))=LOWER(TRIM(:n)) GROUP BY item LIMIT 1");
            $stmt->execute([':n'=>$name]);$row=$stmt->fetch();if($row)return$row;
        }
        $needle=mb_strtolower(trim($name));
        foreach($this->catalogItems() as$item)if(mb_strtolower($item['item'])===$needle)return$item;
        foreach($this->knowledgeItems() as$item)if(mb_strtolower($item['item'])===$needle)return$item;
        return null;
    }
    /** @return array<string,mixed>|null */
    private function findMarketItemByKey(string $key): ?array
    {
        $key=trim($key);
        if($this->tableExists('structured_offers')){
            $stmt=$this->pdo->prepare("SELECT MIN(item_key) item_key,MIN(item) item FROM structured_offers WHERE item_key=:k GROUP BY item_key LIMIT 1");
            $stmt->execute([':k'=>$key]);$row=$stmt->fetch();if($row)return$row;
        }
        foreach($this->catalogItems() as$item)if($item['item_key']===$key)return$item;
        foreach($this->knowledgeItems() as$item)if($item['item_key']===$key)return$item;
        return null;
    }

    /** @return array<int,array{item_key:string,item:string}> */
    private function catalogItems(): array
    {
        static $cache=null;if(is_array($cache))return$cache;
        $cache=[];$file=$this->root.'/app/Data/items.json';if(!is_file($file))return$cache;
        $decoded=json_decode((string)file_get_contents($file),true);if(!is_array($decoded))return$cache;
        foreach($decoded as$row){
            if(!is_array($row))continue;$key=trim((string)($row['key']??''));$name=trim((string)($row['name']??''));
            if($key!==''&&$name!=='')$cache[]=['item_key'=>$key,'item'=>$name];
        }
        return$cache;
    }


    /** @return array<int,array{item_key:string,item:string}> */
    private function knowledgeItems(): array
    {
        if(!$this->tableExists('kb_items')) return [];
        $rows=$this->pdo->query("SELECT key,name FROM kb_items WHERE active=1 AND TRIM(COALESCE(name,''))<>'' ORDER BY name COLLATE NOCASE")->fetchAll();
        $out=[];
        foreach($rows as$row){
            $key=trim((string)($row['key']??''));$name=trim((string)($row['name']??''));
            if($key!==''&&$name!=='')$out[]=['item_key'=>$key,'item'=>$name];
        }
        return$out;
    }

    /** @return array<string,int> */
    public function knowledgeCleanup(): array
    {
        if(!$this->tableExists('kb_items')||!$this->tableExists('kb_aliases')) return ['duplicate_names'=>0,'duplicate_aliases'=>0,'aliases_removed'=>0];
        $duplicateNames=(int)$this->pdo->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(name)) n FROM kb_items WHERE active=1 GROUP BY LOWER(TRIM(name)) HAVING COUNT(*)>1)")->fetchColumn();
        $duplicateAliases=(int)$this->pdo->query("SELECT COUNT(*) FROM (SELECT item_key,normalized_alias FROM kb_aliases GROUP BY item_key,normalized_alias HAVING COUNT(*)>1)")->fetchColumn();
        // Exact duplicate aliases for the same item add no parser knowledge.
        $before=(int)$this->pdo->query('SELECT COUNT(*) FROM kb_aliases')->fetchColumn();
        $this->pdo->exec("DELETE FROM kb_aliases WHERE rowid NOT IN (SELECT MIN(rowid) FROM kb_aliases GROUP BY item_key,normalized_alias)");
        $after=(int)$this->pdo->query('SELECT COUNT(*) FROM kb_aliases')->fetchColumn();
        return ['duplicate_names'=>$duplicateNames,'duplicate_aliases'=>$duplicateAliases,'aliases_removed'=>max(0,$before-$after)];
    }

    private function applyManualOverrides(): void
    {
        $file=$this->root.'/config/item-icons.php';if(!is_file($file))return;
        $map=require$file;if(!is_array($map)||$map===[])return;
        foreach($map as$name=>$datId){
            $id=(int)$datId;if($id<=0)continue;$item=$this->findMarketItemByName((string)$name);$asset=$this->assetByDatId($id);if($item===null||$asset===null)continue;
            $this->upsertItemLink($item,$asset,'curated',1.0,null);
            if(trim((string)($asset['linked_item_key']??''))==='')$this->pdo->prepare("UPDATE item_assets SET linked_item_key=:k,linked_item_name=:n,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':k'=>$item['item_key'],':n'=>$item['item'],':id'=>$asset['id']]);
        }
    }

    /** @return array<string,mixed>|null */
    private function assetById(int $id): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM item_assets WHERE id=:id LIMIT 1');$stmt->execute([':id'=>$id]);$row=$stmt->fetch();return$row?:null;
    }

    /** @return array<string,mixed>|null */
    private function assetByDatId(int $datId): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM item_assets WHERE dat_file_id=:d ORDER BY id DESC LIMIT 1');$stmt->execute([':d'=>$datId]);$row=$stmt->fetch();return$row?:null;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $asset */
    private function upsertItemLink(array $item,array $asset,string $source,float $confidence,?string $sourceTitle): void
    {
        // Prevent an old one-link-per-asset record from becoming a stale
        // fallback after an item is remapped to a better inventory icon.
        $clear=$this->pdo->prepare("UPDATE item_assets SET linked_item_key=NULL,linked_item_name=NULL,updated_at=CURRENT_TIMESTAMP WHERE id<>:asset_id AND linked_item_key=:item_key");
        $clear->execute([':asset_id'=>(int)$asset['id'],':item_key'=>(string)$item['item_key']]);
        $stmt=$this->pdo->prepare("INSERT INTO item_icon_links(item_key,item_name,asset_id,dat_file_id,match_source,confidence,source_title,created_at,updated_at) VALUES(:k,:n,:a,:d,:s,:c,:t,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON CONFLICT(item_key) DO UPDATE SET item_name=excluded.item_name,asset_id=excluded.asset_id,dat_file_id=excluded.dat_file_id,match_source=excluded.match_source,confidence=excluded.confidence,source_title=excluded.source_title,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([':k'=>(string)$item['item_key'],':n'=>(string)$item['item'],':a'=>(int)$asset['id'],':d'=>(int)($asset['dat_file_id']??0),':s'=>$source,':c'=>$confidence,':t'=>$sourceTitle]);
    }

    private function tableExists(string $table): bool { $stmt=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:n LIMIT 1"); $stmt->execute([':n'=>$table]); return (bool)$stmt->fetchColumn(); }
    private function nullableString(mixed $value): ?string { $v=trim((string)($value??'')); return $v!==''?$v:null; }
}
