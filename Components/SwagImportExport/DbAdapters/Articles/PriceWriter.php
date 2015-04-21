<?php

namespace Shopware\Components\SwagImportExport\DbAdapters\Articles;

use Shopware\Components\SwagImportExport\DbalHelper;
use Shopware\Components\SwagImportExport\Exception\AdapterException;
use Shopware\Components\SwagImportExport\Utils\SnippetsHelper;

class PriceWriter
{
    /** @var \Doctrine\DBAL\Connection */
    protected $connection;

    protected $customerGroups;

    public function __construct()
    {
        $this->dbalHelper = new DbalHelper();
        $this->connection = Shopware()->Models()->getConnection();

        $this->customerGroups = $this->getCustomerGroup();
    }

    public function write($articleId, $articleDetailId, $prices)
    {
        $tax = $this->getArticleTaxRate($articleId);

        foreach ($prices as $price) {
            $price = $this->setDefaultValues($price);
            $this->checkRequirements($price);

            // skip empty prices for non-default customer groups
            if ((!isset($price['price']) || empty($price['price'])) && $price['priceGroup'] != 'EK') {
                continue;
            }

            $priceId = null;
            $result = $this->connection->fetchColumn(
                '
                    SELECT id
                    FROM s_articles_prices
                    WHERE articleID = ? AND pricegroup = ? AND `from` = ?
                ',
                array($articleId, $price['priceGroup'], $price['from'])
            );
            if (!empty($result)) {
                $priceId = $result['id'];
            }

            $newPrice = $priceId == 0;
            $price['articleId'] = $articleId;
            $price['articleDetailsId'] = $articleDetailId;
            $price['customerGroupKey'] = $price['priceGroup'];
            $price = $this->calculatePrice($price, $newPrice, $tax);
            $builder = $this->dbalHelper->getQueryBuilderForEntity($price, 'Shopware\Models\Article\Price', $priceId);
            $builder->execute();
        }
    }

    protected function calculatePrice($price, $newPrice, $tax)
    {
        $taxInput = $this->customerGroups[$price['priceGroup']];

        $price['price'] = floatval(str_replace(",", ".", $price['price']));

        if (isset($price['basePrice'])) {
            $price['basePrice'] = floatval(str_replace(",", ".", $price['basePrice']));
        }

        if (isset($price['pseudoPrice'])) {
            $price['pseudoPrice'] = floatval(str_replace(",", ".", $price['pseudoPrice']));
        } else {
            if ($newPrice) {
                $price['pseudoPrice'] = 0;
            }

            if ($taxInput) {
                $price['pseudoPrice'] = round($price['pseudoPrice'] * (100 + $tax) / 100, 2);
            }
        }

        if (isset($price['percent'])) {
            $price['percent'] = floatval(str_replace(",", ".", $price['percent']));
        }

        if ($taxInput) {
            $price['price'] = $price['price'] / (100 + $tax) * 100;
            $price['pseudoPrice'] = $price['pseudoPrice'] / (100 + $tax) * 100;
        }

        return $price;
    }

    protected function checkRequirements($price)
    {
        if (!array_key_exists($price['priceGroup'], $this->customerGroups)) {
            $message = SnippetsHelper::getNamespace()->get(
                'adapters/customerGroup_not_found',
                'Customer Group by key %s not found for article %s'
            );
            throw new AdapterException(sprintf($message, $price['priceGroup'], ''));
        }

        if ((!isset($price['price']) || empty($price['price'])) && $price['priceGroup'] == 'EK') {
            $message = SnippetsHelper::getNamespace()->get(
                'adapters/articles/incorrect_price',
                'Price value is incorrect for article with nubmer %s'
            );
            throw new AdapterException(sprintf($message, ''));
        }

        if ($price['from'] <= 0) {
            $message = SnippetsHelper::getNamespace()->get(
                'adapters/articles/invalid_price',
                'Invalid Price "from" value for article %s'
            );
            throw new AdapterException(sprintf($message, ''));
        }
    }

    /**
     * @param $price
     */
    protected function setDefaultValues($price)
    {
        if (empty($price['priceGroup'])) {
            $price['priceGroup'] = 'EK';
        }

        if (!isset($price['from'])) {
            $price['from'] = 1;
        }

        $price['from'] = intval($price['from']);

        if (isset($price['to'])) {
            $price['to'] = intval($price['to']);
        } else {
            $price['to'] = 0;
        }

        // if the "to" value isn't numeric, set the place holder "beliebig"
        if ($price['to'] <= 0) {
            $price['to'] = 'beliebig';
        }

        return $price;
    }

    private function getCustomerGroup()
    {
        $groups = array();
        $result = $this->connection->fetchAll(
            'SELECT groupkey, taxinput FROM s_core_customergroups'
        );

        foreach ($result as $row) {
            $groups[$row['groupkey']] = $row['taxinput'];
        }

        return $groups;
    }

    /**
     * @param $articleId
     * @return array
     * @throws \Shopware\Components\SwagImportExport\Exception\AdapterException
     */
    protected function getArticleTaxRate($articleId)
    {
        $result = $this->connection->fetchAssoc(
            'SELECT coretax.tax FROM s_core_tax AS coretax
              LEFT JOIN s_articles AS article
              ON article.taxID = coretax.id
              WHERE article.id = ?'
            , array($articleId));

        if (empty($result)) {
            throw new AdapterException("Tax for article $articleId not found");
        }

        return floatval($result['tax']);
    }
}