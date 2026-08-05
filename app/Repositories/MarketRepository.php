<?php
declare(strict_types=1);

namespace LittyWatch\Repositories;

use PDO;

final class MarketRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,int|string|null> */
    public function counters(): array
    {
        return [
            'messages' => (int)$this->pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
            'offers' => (int)$this->pdo->query('SELECT COUNT(*) FROM offers')->fetchColumn(),
            'accepted' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE quality_status='accepted'")->fetchColumn(),
            'review' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE quality_status='review'")->fetchColumn(),
            'buy' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE trade_type='buy'")->fetchColumn(),
            'sell' => (int)$this->pdo->query("SELECT COUNT(*) FROM offers WHERE trade_type='sell'")->fetchColumn(),
            'latest_posted_at' => $this->pdo->query('SELECT MAX(posted_at) FROM messages')->fetchColumn() ?: null,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function latestOffers(string $query = '', string $type = '', string $status = '', int $limit = 150): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(o.item LIKE :q OR o.details LIKE :q OR m.player LIKE :q OR m.message LIKE :q OR o.raw_segment LIKE :q)';
            $params[':q'] = '%'.$query.'%';
        }
        if (in_array($type, ['buy','sell','trade'], true)) {
            $where[] = 'o.trade_type = :type';
            $params[':type'] = $type;
        }
        if (in_array($status, ['accepted','review'], true)) {
            $where[] = 'o.quality_status = :status';
            $params[':status'] = $status;
        }

        $sql = 'SELECT o.*,m.player,m.message,m.posted_at FROM offers o JOIN messages m ON m.id=o.message_id'
            . ($where ? ' WHERE '.implode(' AND ', $where) : '')
            . ' ORDER BY datetime(m.posted_at) DESC,o.id DESC LIMIT '.max(1, min(500, $limit));
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
