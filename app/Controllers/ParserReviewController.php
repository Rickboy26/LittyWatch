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
            'reasonGroups' => $this->repo->reasonGroups(),
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
        $previousDisplayErrors = ini_get('display_errors');
        $previousHandler = set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file,
                int $line
            ): never {
                if (!(error_reporting() & $severity)) {
                    throw new \ErrorException($message, 0, $severity, $file, $line);
                }

                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );

        ini_set('display_errors', '0');
        ob_start();

        try {
            $result = $this->batchReview->process(
                $request->int('cursor'),
                $request->int('limit', 150)
            );

            $unexpectedOutput = trim((string)ob_get_clean());
            if ($unexpectedOutput !== '') {
                error_log(
                    'Unexpected batch review output: '
                    . mb_substr(strip_tags($unexpectedOutput), 0, 2000)
                );
            }

            return Response::json([
                'ok' => true,
                ...$result,
            ]);
        } catch (Throwable $exception) {
            $unexpectedOutput = trim((string)ob_get_clean());

            error_log(
                'Batch review endpoint failed: '
                . $exception
                . ($unexpectedOutput !== ''
                    ? PHP_EOL . 'Buffered output: ' . strip_tags($unexpectedOutput)
                    : '')
            );

            return Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
                'error_type' => $exception::class,
                'error_location' => basename($exception->getFile())
                    . ':' . $exception->getLine(),
            ], 500);
        } finally {
            ini_set(
                'display_errors',
                $previousDisplayErrors === false
                    ? '0'
                    : (string)$previousDisplayErrors
            );

            if ($previousHandler !== null) {
                set_error_handler($previousHandler);
            } else {
                restore_error_handler();
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
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
