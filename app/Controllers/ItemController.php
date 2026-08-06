<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\Repositories\MarketRepository;

final class ItemController
{
    public function __construct(
        private readonly MarketRepository $market,
        private readonly View $view,
    ) {}

    public function index(Request $request): Response
    {
        $query = trim($request->string('q'));
        $items = $this->market->itemDirectory($query, 250);

        return Response::html($this->view->render('items/index', [
            'title' => 'Items · LittyWatch',
            'query' => $query,
            'items' => $items,
        ]));
    }

    public function show(Request $request): Response
    {
        $name = trim($request->string('name'));
        if ($name === '') {
            return Response::html('<h1>Item ontbreekt</h1><p>Geef een itemnaam mee.</p>', 400);
        }

        $canonicalName = $this->market->canonicalItemName($name) ?? $name;
        $item = $this->market->itemSummary($canonicalName);
        if ($item === null) {
            return Response::html($this->view->render('items/not-found', [
                'title' => 'Item niet gevonden · LittyWatch',
                'name' => $name,
            ]), 404);
        }
        $name = (string)$item['item'];

        $scope = $request->string('scope');
        if (!in_array($scope, ['30','100','all'], true)) $scope = '100';
        $variant = trim($request->string('variant'));
        $variants = $this->market->variantsForItem($name);

        return Response::html($this->view->render('items/show', [
            'title' => $name.' · LittyWatch',
            'item' => $item,
            'offers' => $this->market->offersForItem($name, 200),
            'buyOffers' => $this->market->activeOffersForItem($name, 'buy', 30),
            'sellOffers' => $this->market->activeOffersForItem($name, 'sell', 30),
            'bestOffers' => $this->market->bestOffersForItem($name),
            'variants' => $variants,
            'scope' => $scope,
            'selectedVariant' => $variant,
            'analytics' => $this->market->itemAnalytics($name, $scope, $variant),
        ]));
    }
}
