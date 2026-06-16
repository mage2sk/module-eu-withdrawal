<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Renders the order increment id as a link to the sales order admin page.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class OrderLink extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['increment_id'])) {
                continue;
            }
            $label = $item['increment_id'];
            if (!empty($item['order_id'])) {
                $url = $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $item['order_id']]);
                $item[$name] = '<a href="' . $url . '">' . $label . '</a>';
            } else {
                $item[$name] = $label;
            }
        }
        return $dataSource;
    }
}
