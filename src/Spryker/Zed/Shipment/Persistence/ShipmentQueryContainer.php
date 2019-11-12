<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Persistence;

use Orm\Zed\Tax\Persistence\Map\SpyTaxRateTableMap;
use Orm\Zed\Tax\Persistence\Map\SpyTaxSetTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Shared\Tax\TaxConstants;
use Spryker\Zed\Kernel\Persistence\AbstractQueryContainer;

/**
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentPersistenceFactory getFactory()
 */
class ShipmentQueryContainer extends AbstractQueryContainer implements ShipmentQueryContainerInterface
{
    public const COL_MAX_TAX_RATE = 'MaxTaxRate';

    /**
     * @api
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentCarrierQuery
     */
    public function queryCarriers()
    {
        return $this->getFactory()->createShipmentCarrierQuery();
    }

    /**
     * @api
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    public function queryMethodPrices()
    {
        return $this->getFactory()->createShipmentMethodPriceQuery();
    }

    /**
     * @api
     *
     * @param int $idShipmentMethod
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    public function queryMethodPricesByIdShipmentMethod($idShipmentMethod)
    {
        return $this->getFactory()
            ->createShipmentMethodPriceQuery()
            ->filterByFkShipmentMethod($idShipmentMethod);
    }

    /**
     * @api
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryMethods()
    {
        return $this->getFactory()->createShipmentMethodQuery();
    }

    /**
     * @api
     *
     * @deprecated Use \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface::getActiveShipmentMethods() instead.
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryActiveMethods()
    {
        return $this->queryMethods()->filterByIsActive(true);
    }

    /**
     * @api
     *
     * @param int $idShipmentMethod
     * @param int $idStore
     * @param int $idCurrency
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    public function queryMethodPriceByShipmentMethodAndStoreCurrency($idShipmentMethod, $idStore, $idCurrency)
    {
        return $this->queryMethodPrices()
            ->filterByFkShipmentMethod($idShipmentMethod)
            ->filterByFkStore($idStore)
            ->filterByFkCurrency($idCurrency);
    }

    /**
     * @api
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryMethodsWithMethodPricesAndCarrier()
    {
        return $this->queryMethods()
            ->joinWithShipmentMethodPrice()
            ->leftJoinWithShipmentCarrier();
    }

    /**
     * @api
     *
     * @param int $idShipmentMethod
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryMethodWithMethodPricesAndCarrierById($idShipmentMethod)
    {
        return $this
            ->queryMethodsWithMethodPricesAndCarrier()
            ->filterByIdShipmentMethod($idShipmentMethod);
    }

    /**
     * @api
     *
     * @param int $idShipmentMethod
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryActiveMethodsWithMethodPricesAndCarrierById($idShipmentMethod)
    {
        return $this
            ->queryMethodsWithMethodPricesAndCarrier()
            ->filterByIdShipmentMethod($idShipmentMethod)
            ->filterByIsActive(true);
    }

    /**
     * @api
     *
     * @deprecated Use \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface::findTaxSetByShipmentMethodAndCountryIso2Code() instead.
     *
     * @param int $idShipmentMethod
     * @param string $countryIso2Code
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function queryTaxSetByIdShipmentMethodAndCountryIso2Code($idShipmentMethod, $countryIso2Code)
    {
        return $this->getFactory()->createShipmentMethodQuery()
            ->filterByIdShipmentMethod($idShipmentMethod)
            ->useTaxSetQuery()
                ->useSpyTaxSetTaxQuery()
                    ->useSpyTaxRateQuery()
                        ->useCountryQuery()
                            ->filterByIso2Code($countryIso2Code)
                        ->endUse()
                        ->_or()
                        ->filterByName(TaxConstants::TAX_EXEMPT_PLACEHOLDER)
                    ->endUse()
                ->endUse()
                ->withColumn(SpyTaxSetTableMap::COL_NAME)
                ->groupBy(SpyTaxSetTableMap::COL_NAME)
                ->withColumn('MAX(' . SpyTaxRateTableMap::COL_RATE . ')', self::COL_MAX_TAX_RATE)
            ->endUse()
            ->select([self::COL_MAX_TAX_RATE]);
    }

    /**
     * @api
     *
     * @deprecated Use \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface::findShipmentTransfersByOrder() instead.
     *
     * @param int $idSalesOrder
     *
     * @return \Orm\Zed\Sales\Persistence\SpySalesShipmentQuery
     */
    public function querySalesShipmentByIdSalesOrder($idSalesOrder)
    {
        return $this->getFactory()
            ->createSalesShipmentQuery()
            ->leftJoinWithSpySalesOrderItem()
            ->filterByFkSalesOrder($idSalesOrder);
    }

    /**
     * @api
     *
     * @deprecated Will be removed without replacement.
     *
     * @param string $carrierName
     * @param int|null $idCarrier
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentCarrierQuery
     */
    public function queryUniqueCarrierName($carrierName, $idCarrier = null)
    {
        $query = $this->getFactory()
            ->createShipmentCarrierQuery()
            ->filterByName($carrierName);

        if ($idCarrier) {
            $query->filterByIdShipmentCarrier($idCarrier, Criteria::NOT_EQUAL);
        }

        return $query;
    }
}
