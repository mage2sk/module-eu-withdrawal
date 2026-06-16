<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const URL_VIEW = 'panth_euwithdrawal/request/view';
    private const URL_DELETE = 'panth_euwithdrawal/request/delete';

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
            if (empty($item['request_id'])) {
                continue;
            }
            $id = $item['request_id'];
            $item[$name]['view'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_VIEW, ['request_id' => $id]),
                'label' => __('View'),
            ];
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_DELETE, ['request_id' => $id]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete withdrawal request'),
                    'message' => __('Are you sure you want to delete this withdrawal request?'),
                ],
                'post' => true,
            ];
        }
        return $dataSource;
    }
}
