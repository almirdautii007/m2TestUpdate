<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\TaxImportExport\Controller\Adminhtml\Rate;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\TaxImportExport\Controller\Adminhtml\Rate;

class ExportPost extends Rate implements HttpPostActionInterface
{
    /**
     * Export action from import/export tax
     *
     * @return ResponseInterface
     */
    public function execute()
    {
        /** start csv content and set template */
        $headers = new \Magento\Framework\DataObject(
            [
                'code' => __('Code'),
                'country_name' => __('Country'),
                'region_name' => __('State'),
                'tax_postcode' => __('Zip/Post Code'),
                'rate' => __('Rate'),
                'zip_is_range' => __('Zip/Post is Range'),
                'zip_from' => __('Range From'),
                'zip_to' => __('Range To'),
            ]
        );
        $template = '"{{code}}","{{country_name}}","{{region_name}}","{{tax_postcode}}","{{rate}}"' .
            ',"{{zip_is_range}}","{{zip_from}}","{{zip_to}}"';
        $content = $headers->toString($template);

        $storeTaxTitleTemplate = [];
        $taxCalculationRateTitleDict = [];

        foreach ($this->_objectManager->create(
            \Magento\Store\Model\Store::class
        )->getCollection()->setLoadDefault(
            false
        ) as $store) {
            $storeTitle = 'title_' . $store->getId();
            $content .= ',"' . $store->getCode() . '"';
            $template .= ',"{{' . $storeTitle . '}}"';
            $storeTaxTitleTemplate[$storeTitle] = null;
        }
        unset($store);

        $content .= "\n";

        foreach ($this->_objectManager->create(
            \Magento\Tax\Model\Calculation\Rate\Title::class
        )->getCollection() as $title) {
            $rateId = $title->getTaxCalculationRateId();

            if (!array_key_exists($rateId, $taxCalculationRateTitleDict)) {
                $taxCalculationRateTitleDict[$rateId] = $storeTaxTitleTemplate;
            }

            $taxCalculationRateTitleDict[$rateId]['title_' . $title->getStoreId()] = $title->getValue();
        }
        unset($title);

        $collection = $this->_objectManager->create(
            \Magento\Tax\Model\ResourceModel\Calculation\Rate\Collection::class
        )->joinCountryTable()->joinRegionTable();

        while ($rate = $collection->fetchItem()) {
            if ($rate->getTaxRegionId() == 0) {
                $rate->setRegionName('*');
            }

            if (array_key_exists($rate->getId(), $taxCalculationRateTitleDict)) {
                $rate->addData($taxCalculationRateTitleDict[$rate->getId()]);
            } else {
                $rate->addData($storeTaxTitleTemplate);
            }

            $content .= $rate->toString($template) . "\n";
        }
        // pass 'rm' parameter to delete a file after download
        $fileContent = ['type' => 'string', 'value' => $content, 'rm' => true];

        return $this->fileFactory->create('tax_rates.csv', $fileContent, DirectoryList::VAR_DIR);
    }
}
