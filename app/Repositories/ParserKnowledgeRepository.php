<?php
declare(strict_types=1);
namespace LittyWatch\Repositories;
use PDO;
final class ParserKnowledgeRepository{
 public function __construct(private readonly PDO $pdo){}
 public function install():void{
  $this->pdo->exec("CREATE TABLE IF NOT EXISTS parser_aliases(id INTEGER PRIMARY KEY AUTOINCREMENT,alias TEXT NOT NULL UNIQUE,item_name TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $this->pdo->exec("CREATE TABLE IF NOT EXISTS parser_exclusions(id INTEGER PRIMARY KEY AUTOINCREMENT,phrase TEXT NOT NULL UNIQUE,kind TEXT NOT NULL DEFAULT 'noise',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $this->pdo->exec("CREATE TABLE IF NOT EXISTS parser_set_sizes(id INTEGER PRIMARY KEY AUTOINCREMENT,item_name TEXT NOT NULL UNIQUE,set_size REAL NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
  $this->pdo->exec("CREATE TABLE IF NOT EXISTS parser_corrections(id INTEGER PRIMARY KEY AUTOINCREMENT,parser_review_id INTEGER,message_id INTEGER,action TEXT NOT NULL,alias TEXT,item_name TEXT,notes TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
 }
 public function aliases():array{$this->install();$rows=$this->pdo->query("SELECT alias,item_name FROM parser_aliases ORDER BY alias")->fetchAll();$out=[];foreach($rows as$r)$out[mb_strtolower(trim((string)$r['alias']))]=(string)$r['item_name'];return$out;}
 public function aliasRows():array{$this->install();return$this->pdo->query("SELECT * FROM parser_aliases ORDER BY alias")->fetchAll();}
 public function exclusionRows():array{$this->install();return$this->pdo->query("SELECT * FROM parser_exclusions ORDER BY phrase")->fetchAll();}
 public function setSizes():array{$this->install();$rows=$this->pdo->query("SELECT item_name,set_size FROM parser_set_sizes")->fetchAll();$out=[];foreach($rows as$r)$out[mb_strtolower(trim((string)$r['item_name']))]=(float)$r['set_size'];return$out;}
 public function setSizeRows():array{$this->install();return$this->pdo->query("SELECT * FROM parser_set_sizes ORDER BY item_name")->fetchAll();}
 public function corrections(int$limit=50):array{$this->install();return$this->pdo->query("SELECT * FROM parser_corrections ORDER BY id DESC LIMIT ".max(1,min(500,$limit)))->fetchAll();}
 public function addAlias(string$a,string$i):void{$this->install();$s=$this->pdo->prepare("INSERT INTO parser_aliases(alias,item_name)VALUES(?,?) ON CONFLICT(alias) DO UPDATE SET item_name=excluded.item_name");$s->execute([mb_strtolower(trim($a)),trim($i)]);}
 public function addExclusion(string$p,string$k):void{$this->install();$s=$this->pdo->prepare("INSERT INTO parser_exclusions(phrase,kind)VALUES(?,?) ON CONFLICT(phrase) DO UPDATE SET kind=excluded.kind");$s->execute([mb_strtolower(trim($p)),trim($k)]);}
 public function addSetSize(string$i,float$n):void{$this->install();$s=$this->pdo->prepare("INSERT INTO parser_set_sizes(item_name,set_size)VALUES(?,?) ON CONFLICT(item_name) DO UPDATE SET set_size=excluded.set_size");$s->execute([trim($i),$n]);}
 public function delete(string$table,int$id):void{if(!in_array($table,['parser_aliases','parser_exclusions','parser_set_sizes'],true))return;$s=$this->pdo->prepare("DELETE FROM {$table} WHERE id=?");$s->execute([$id]);}
 public function log(int$reviewId,int$messageId,string$action,string$alias='',string$item='',string$notes=''):void{$this->install();$s=$this->pdo->prepare("INSERT INTO parser_corrections(parser_review_id,message_id,action,alias,item_name,notes)VALUES(?,?,?,?,?,?)");$s->execute([$reviewId,$messageId,$action,trim($alias),trim($item),trim($notes)]);}
}
