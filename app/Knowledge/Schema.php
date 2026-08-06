<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
use PDO;
final class Schema {
  public static function install(PDO $db): void {
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS kb_categories (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 parent_key TEXT,
 source TEXT NOT NULL DEFAULT 'local'
);
CREATE TABLE IF NOT EXISTS kb_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 category_key TEXT NOT NULL DEFAULT 'unknown',
 source TEXT NOT NULL DEFAULT 'local',
 source_id TEXT,
 metadata_json TEXT NOT NULL DEFAULT '{}',
 active INTEGER NOT NULL DEFAULT 1,
 updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_kb_items_category ON kb_items(category_key);
CREATE TABLE IF NOT EXISTS kb_aliases (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 item_key TEXT NOT NULL,
 alias TEXT NOT NULL,
 normalized_alias TEXT NOT NULL,
 source TEXT NOT NULL DEFAULT 'local',
 UNIQUE(item_key, normalized_alias),
 FOREIGN KEY(item_key) REFERENCES kb_items(key) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_kb_aliases_normalized ON kb_aliases(normalized_alias);
CREATE TABLE IF NOT EXISTS kb_groups (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 aliases_json TEXT NOT NULL DEFAULT '[]',
 item_keys_json TEXT NOT NULL DEFAULT '[]',
 source TEXT NOT NULL DEFAULT 'local'
);
CREATE TABLE IF NOT EXISTS kb_attributes (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 profession TEXT,
 aliases_json TEXT NOT NULL DEFAULT '[]'
);
CREATE TABLE IF NOT EXISTS kb_profiles (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 key TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL,
 description TEXT NOT NULL DEFAULT '',
 track_json TEXT NOT NULL DEFAULT '[]',
 ignore_json TEXT NOT NULL DEFAULT '[]',
 market_key_json TEXT NOT NULL DEFAULT '[]'
);
CREATE TABLE IF NOT EXISTS kb_item_profiles (
 item_key TEXT PRIMARY KEY,
 profile_key TEXT NOT NULL,
 source TEXT NOT NULL DEFAULT 'local',
 FOREIGN KEY(item_key) REFERENCES kb_items(key) ON DELETE CASCADE,
 FOREIGN KEY(profile_key) REFERENCES kb_profiles(key) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS kb_category_profiles (
 category_key TEXT PRIMARY KEY,
 profile_key TEXT NOT NULL,
 source TEXT NOT NULL DEFAULT 'local',
 FOREIGN KEY(profile_key) REFERENCES kb_profiles(key) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS kb_import_runs (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 source TEXT NOT NULL,
 status TEXT NOT NULL,
 items_seen INTEGER NOT NULL DEFAULT 0,
 items_written INTEGER NOT NULL DEFAULT 0,
 notes TEXT,
 created_at TEXT NOT NULL
);
SQL);
  }
}
