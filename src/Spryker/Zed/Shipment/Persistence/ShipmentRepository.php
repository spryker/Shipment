<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Persistence;

use ArrayObject;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentPriceTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;
use Generated\Shared\Transfer\TaxSetTransfer;
use Orm\Zed\Sales\Persistence\Map\SpySalesOrderItemTableMap;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery;
use Orm\Zed\Tax\Persistence\Map\SpyTaxRateTableMap;
use Orm\Zed\Tax\Persistence\Map\SpyTaxSetTableMap;
use Spryker\Shared\Tax\TaxConstants;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;

/**
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentPersistenceFactory getFactory()
 */
class ShipmentRepository extends AbstractRepository implements ShipmentRepositoryInterface
{
    protected const COL_MAX_TAX_RATE = 'maxTaxRate';

    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $methodTransfer
     * @param string $countryIso2Code
     *
     * @return \Generated\Shared\Transfer\TaxSetTransfer|null
     */
    public function findTaxSetByShipmentMethodAndCountryIso2Code(
        ShipmentMethodTransfer $methodTransfer,
        string $countryIso2Code
    ): ?TaxSetTransfer {
        $shipmentMethodEntity = $this->getFactory()
            ->createShipmentMethodQuery()
            ->filterByIdShipmentMethod($methodTransfer->getIdShipmentMethod())
            ->leftJoinWithTaxSet()
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
                ->groupBy(SpyTaxSetTableMap::COL_NAME)
                ->withColumn('MAX(' . SpyTaxRateTableMap::COL_RATE . ')', static::COL_MAX_TAX_RATE)
            ->endUse()
            ->findOne();

        if ($shipmentMethodEntity === null || $shipmentMethodEntity->getTaxSet() === null) {
            return null;
        }

        $taxSetTransfer = $this->getFactory()
            ->createTaxSetMapper()
            ->mapTaxSetEntityToTaxSetTransfer($shipmentMethodEntity->getTaxSet(), new TaxSetTransfer());

        return $taxSetTransfer->setEffectiveRate($shipmentMethodEntity->getVirtualColumn(static::COL_MAX_TAX_RATE));
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer[]
     */
    public function findShipmentTransfersByOrder(OrderTransfer $orderTransfer): array
    {
        $salesOrderShipments = $this->getFactory()
            ->createSalesShipmentQuery()
            ->leftJoinWithSpySalesOrderAddress()
            ->filterByFkSalesOrder($orderTransfer->getIdSalesOrder())
            ->find();

        if ($salesOrderShipments->count() === 0) {
            return [];
        }

        return $this->hydrateShipmentTransfersFromShipmentEntities($salesOrderShipments);
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return int[][]
     */
    public function getItemIdsGroupedByShipmentIds(OrderTransfer $orderTransfer): array
    {
        $salesOrderItemIdsWithShipmentIds = $this->getFactory()
            ->createSalesOrderItemQuery()
            ->filterByFkSalesOrder($orderTransfer->getIdSalesOrder())
            ->select([SpySalesOrderItemTableMap::COL_FK_SALES_SHIPMENT, SpySalesOrderItemTableMap::COL_ID_SALES_ORDER_ITEM])
            ->find();

        if ($salesOrderItemIdsWithShipmentIds->count() === 0) {
            return [];
        }

        return $this->groupSalesOrderItemIdsByShipmentId($salesOrderItemIdsWithShipmentIds);
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer[]
     */
    public function findShipmentMethodTransfersByShipment(array $shipmentTransfers): array
    {
        if (count($shipmentTransfers) === 0) {
            return [];
        }

        $shipmentMethodNames = $this->getShipmentMethodNames($shipmentTransfers);

        $salesShipmentMethods = $this->getFactory()
            ->createShipmentMethodQuery()
            ->filterByIsActive(true)
            ->filterByName_In($shipmentMethodNames)
            ->find();

        if ($salesShipmentMethods->count() === 0) {
            return [];
        }

        return $this->hydrateShipmentMethodTransfersFromShipmentMethodEntities($salesShipmentMethods);
    }

    /**
     * @param string $shipmentMethodName
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    public function findShipmentMethodByName(string $shipmentMethodName): ?ShipmentMethodTransfer
    {
        $salesShipmentMethodEntity = $this->queryMethodsWithMethodPricesAndCarrier()
            ->filterByName($shipmentMethodName)
            ->findOne();

        if ($salesShipmentMethodEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createShipmentMethodMapper()
            ->mapShipmentMethodEntityToShipmentMethodTransfer($salesShipmentMethodEntity, new ShipmentMethodTransfer());
    }

    /**
     * @param int $idShipmentMethod
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer|null
     */
    public function findShipmentById(int $idShipmentMethod): ?ShipmentTransfer
    {
        $salesShipmentEntity = $this->getFactory()
            ->createSalesShipmentQuery()
            ->filterByIdSalesShipment($idShipmentMethod)
            ->findOne();

        if ($salesShipmentEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createShipmentMapper()
            ->mapShipmentEntityToShipmentTransfer($salesShipmentEntity, new ShipmentTransfer());
    }

    /**
     * @return \ArrayObject|\Generated\Shared\Transfer\ShipmentMethodTransfer[]
     */
    public function getActiveShipmentMethods(): ArrayObject
    {
        $shipmentMethodList = new ArrayObject();
        $shipmentMethodEntities = $this->queryActiveMethodsWithMethodPricesAndCarrier()->find();

        if ($shipmentMethodEntities->count() === 0) {
            return $shipmentMethodList;
        }

        $shipmentMethodMapper = $this->getFactory()->createShipmentMethodMapper();
        foreach ($shipmentMethodEntities as $shipmentMethodEntity) {
            $shipmentMethodTransfer = $shipmentMethodMapper
                    ->mapShipmentMethodEntityToShipmentMethodTransfer($shipmentMethodEntity, new ShipmentMethodTransfer());

            $shipmentMethodList->append($shipmentMethodTransfer);
        }

        return $shipmentMethodList;
    }

    /**
     * @param int $idShipmentMethod
     * @param int $idStore
     * @param int $idCurrency
     *
     * @return \Generated\Shared\Transfer\ShipmentPriceTransfer|null
     */
    public function findShipmentMethodPrice(int $idShipmentMethod, int $idStore, int $idCurrency): ?ShipmentPriceTransfer
    {
        $shipmentMethodPriceEntity = $this->queryMethodPriceByShipmentMethodAndStoreCurrency(
            $idShipmentMethod,
            $idStore,
            $idCurrency
        )
            ->findOne();

        if ($shipmentMethodPriceEntity === null) {
            return null;
        }

        return $this->getFactory()->createShipmentMethodMapper()
            ->mapShipmentMethodPriceEntityToShipmentPriceTransfer($shipmentMethodPriceEntity, new ShipmentPriceTransfer());
    }

    /**
     * @param int $idShipmentMethod
     *
     * @return bool
     */
    public function hasShipmentMethodByIdShipmentMethod(int $idShipmentMethod): bool
    {
        return $this->getFactory()
            ->createShipmentMethodQuery()
            ->filterByIdShipmentMethod($idShipmentMethod)
            ->exists();
    }

    /**
     * @param int $idSalesOrder
     *
     * @return \Generated\Shared\Transfer\OrderTransfer|null
     */
    public function findSalesOrderById(int $idSalesOrder): ?OrderTransfer
    {
        $salesOrderEntity = $this->getFactory()
            ->createSalesOrderQuery()
            ->filterByIdSalesOrder($idSalesOrder)
            ->findOne();

        if ($salesOrderEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createShipmentOrderMapper()
            ->mapSalesOrderEntityToOrderTransfer($salesOrderEntity, new OrderTransfer());
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     *
     * @return string[]
     */
    protected function getShipmentMethodNames(iterable $shipmentTransfers): array
    {
        $shipmentMethodNames = [];
        foreach ($shipmentTransfers as $shipmentTransfer) {
            $shipmentMethodTransfer = $shipmentTransfer->getMethod();
            if ($shipmentMethodTransfer === null) {
                continue;
            }

            $shipmentMethodName = $shipmentMethodTransfer->getName();
            if ($shipmentMethodName === '') {
                continue;
            }

            $shipmentMethodNames[$shipmentMethodName] = $shipmentMethodName;
        }

        return $shipmentMethodNames;
    }

    /**
     * @param iterable|\Orm\Zed\Sales\Persistence\SpySalesShipment[]|\Propel\Runtime\Collection\ObjectCollection $salesOrderShipments
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer[]
     */
    protected function hydrateShipmentTransfersFromShipmentEntities(
        iterable $salesOrderShipments
    ): array {
        $shipmentMapper = $this->getFactory()->createShipmentMapper();
        $shipmentTransfers = [];

        foreach ($salesOrderShipments as $salesShipmentEntity) {
            $shipmentTransfers[] = $shipmentMapper->mapShipmentEntityToShipmentTransferWithDetails(new ShipmentTransfer(), $salesShipmentEntity);
        }

        return $shipmentTransfers;
    }

    /**
     * @param iterable|\Orm\Zed\Shipment\Persistence\SpyShipmentMethod[]|\Propel\Runtime\Collection\ObjectCollection $salesShipmentMethods
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer[]
     */
    protected function hydrateShipmentMethodTransfersFromShipmentMethodEntities(
        iterable $salesShipmentMethods
    ): array {
        $shipmentMapper = $this->getFactory()->createShipmentMapper();
        $shipmentMethodTransfers = [];

        foreach ($salesShipmentMethods as $salesShipmentMethodEntity) {
            $shipmentMethodTransfers[] = $shipmentMapper->mapShipmentMethodEntityToShipmentMethodTransfer(new ShipmentMethodTransfer(), $salesShipmentMethodEntity);
        }

        return $shipmentMethodTransfers;
    }

    /**
     * @param iterable|array $salesOrderItemIdsWithShipmentIds
     *
     * @return int[][]
     */
    protected function groupSalesOrderItemIdsByShipmentId(iterable $salesOrderItemIdsWithShipmentIds): array
    {
        $groupedResult = [];

        foreach ($salesOrderItemIdsWithShipmentIds as [
            SpySalesOrderItemTableMap::COL_FK_SALES_SHIPMENT => $shipmentId,
            SpySalesOrderItemTableMap::COL_ID_SALES_ORDER_ITEM => $orderItemId,
        ]) {
            if (!isset($groupedResult[$shipmentId])) {
                $groupedResult[$shipmentId] = [];
            }

            $groupedResult[$shipmentId][] = $orderItemId;
        }

        return $groupedResult;
    }

    /**
     * @module Currency
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    protected function queryMethodsWithMethodPricesAndCarrier(): SpyShipmentMethodQuery
    {
        return $this->queryMethods()
            ->joinWithShipmentMethodPrice()
                ->useShipmentMethodPriceQuery()
                    ->joinWithCurrency()
                ->endUse()
            ->leftJoinWithShipmentCarrier();
    }

    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    protected function queryMethods(): SpyShipmentMethodQuery
    {
        return $this->getFactory()->createShipmentMethodQuery();
    }

    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    protected function queryActiveMethodsWithMethodPricesAndCarrier(): SpyShipmentMethodQuery
    {
        return $this
            ->queryMethodsWithMethodPricesAndCarrier()
            ->filterByIsActive(true);
    }

    /**
     * @param int $idShipmentMethod
     * @param int $idStore
     * @param int $idCurrency
     *
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    protected function queryMethodPriceByShipmentMethodAndStoreCurrency(
        int $idShipmentMethod,
        int $idStore,
        int $idCurrency
    ): SpyShipmentMethodPriceQuery {
        return $this->queryMethodPrices()
            ->filterByFkShipmentMethod($idShipmentMethod)
            ->filterByFkStore($idStore)
            ->filterByFkCurrency($idCurrency);
    }

    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    protected function queryMethodPrices(): SpyShipmentMethodPriceQuery
    {
        return $this->getFactory()->createShipmentMethodPriceQuery();
    }
}
