<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Ui\Component\Listing\Column;

use Magento\Store\Ui\Component\Listing\Column\Store as BaseStore;

class StoreView extends BaseStore
{

    protected function prepareItem(array $item)
    {
        $stores = $item[$this->storeKey] ?? null;
        if ($stores === null || $stores === '' || $stores === []) {
            return '';
        }
        if (!is_array($stores)) {
            $stores = explode(',', (string) $stores);
        }
        $stores = array_map('intval', $stores);
        if (in_array(0, $stores, true)) {
            return (string) __('All Store Views');
        }
        $labels = [];
        foreach ($this->systemStore->getStoresStructure(false, $stores) as $website) {
            foreach ($website['children'] ?? [] as $group) {
                foreach ($group['children'] ?? [] as $store) {
                    $labels[] = $this->escaper->escapeHtml($store['label']);
                }
            }
        }
        return implode('<br/>', $labels);
    }
}
