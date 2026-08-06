<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Repositories\ItemKnowledgeRepository;
use LittyWatch\Repositories\ParserReviewRepository;
use LittyWatch\Services\ParserBatchReviewService;
use Throwable;

final class ParserReviewController
{
    public function __construct(
        private readonly ParserReviewRepository $repo,
        private readonly ItemKnowledgeRepository $itemKnowledge,
        private readonly ParserBatchReviewService $batchReview,
        private readonly View $view,
    ) {}

    public function index(Request $request): Response
    {
        $this->repo->seedPending();

        $status = $request->string('status', 'pending');
        $quality = $request->string('quality');
        $query = $request->string('q');
        $tab = $request->string('tab', 'queue');
        $selectedId = $request->int('selected');

        $rows = $this->repo->queue($status, $quality, $query, 200);
        if ($selectedId <= 0 && $rows !== []) {
            $selectedId = (int)$rows[0]['id'];
        }

        $selected = null;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $selectedId) {
                $selected = $row;
                break;
            }
        }

        $selectedKnowledge = null;
        if ($selected !== null) {
            $selectedKnowledge = $this->itemKnowledge->find((string)$selected['item']);
        }

        return Response::html($this->view->render('reviews/index', [
            'title' => 'Parser Review · LittyWatch',
            'summary' => $this->repo->summary(),
            'qualityStats' => $this->repo->qualityStats(),
            'rows' => $rows,
            'selected' => $selected,
            'selectedKnowledge' => $selectedKnowledge,
            'terms' => $this->repo->commonTerms(),
            'knowledge' => $this->repo->knowledge(),
            'itemKnowledgeRows' => $this->itemKnowledge->all($request->string('knowledge_q'), 300),
            'status' => $status,
            'quality' => $quality,
            'query' => $query,
            'tab' => $tab,
            'message' => $request->string('message'),
            'error' => $request->string('error'),
        ]));
    }

    public function update(Request $request): Response
    {
        try {
            $action = $request->string('action', 'review');

            if ($action === 'review') {
                $this->repo->save(
                    $request->int('id'),
                    $request->string('review_status'),
                    $request->string('expected_item'),
                    $request->string('expected_requirement') !== ''
                        ? $request->int('expected_requirement')
                        : null,
                    $request->string('expected_attribute'),
                    $request->string('expected_market_key'),
                    $request->string('notes'),
                    $request->string('alias')
                );
            } elseif ($action === 'save_item_knowledge') {
                $this->itemKnowledge->save($request->post);
            } elseif ($action === 'delete_item_knowledge') {
                $this->itemKnowledge->delete($request->int('id'));
            } else {
                $this->repo->knowledgeAction($action, $request->post);
            }

            $location = '/parser-review?tab=' . rawurlencode($request->string('return_tab', 'queue'))
                . '&status=' . rawurlencode($request->string('return_status', 'pending'))
                . '&selected=' . $request->int('selected')
                . '&message=' . rawurlencode('Opgeslagen');

            return new Response('', 302, ['Location' => $location]);
        } catch (Throwable $exception) {
            return new Response('', 302, [
                'Location' => '/parser-review?error=' . rawurlencode($exception->getMessage())
            ]);
        }
    }

    public function batchReview(Request $request): Response
    {
        try {
            return Response::json([
                'ok' => true,
                ...$this->batchReview->process(
                    $request->int('cursor'),
                    $request->int('limit', 150)
                ),
            ]);
        } catch (Throwable $exception) {
            return Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request): Response
    {
        return Response::json([
            'version' => 'v4.4',
            'exported_at' => date(DATE_ATOM),
            'cases' => $this->repo->export(),
            'item_knowledge' => $this->itemKnowledge->all('', 1000),
        ]);
    }
}
