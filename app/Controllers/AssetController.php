<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\V2\Assets\AssetCatalogService;

final class AssetController
{
    public function __construct(
        private readonly AssetCatalogService $assets,
        private readonly View $view,
        private readonly string $root,
    ) {}

    public function index(Request $request): Response
    {
        $directory = $this->root . '/assets/game-items';
        $files = 0;
        if (is_dir($directory)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && preg_match('/item[_-]?icon[_-]?\d+\.(png|jpe?g|webp|gif)$/i', $file->getFilename())) {
                    $files++;
                }
            }
        }
        $summary = $this->assets->summary();
        return Response::html($this->view->render('assets/index', [
            'title' => 'Inventory icons · LittyWatch',
            'count' => $files,
            'summary' => $summary,
            'directory' => $directory,
        ]));
    }
}
