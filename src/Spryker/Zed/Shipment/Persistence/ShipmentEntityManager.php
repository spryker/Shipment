<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Persistence;

use Generated\Shared\Transfer\ExpenseTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;
use Orm\Zed\Sales\Persistence\SpySalesExpense;
use Orm\Zed\Sales\Persistence\SpySalesShipment;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethod;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentPersistenceFactory getFactory()
 */
class ShipmentEntityManager extends AbstractEntityManager implements ShipmentEntityManagerInterface
{
    /**
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \Generated\Shared\Transfer\ExpenseTransfer|null $expenseTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer
     */
    public function saveSalesShipment(
        ShipmentTransfer $shipmentTransfer,
        OrderTransfer $orderTransfer,
        ?ExpenseTransfer $expenseTransfer = null
    ): ShipmentTransfer {
        $salesShipmentEntity = null;
        $idSalesShipment = $shipmentTransfer->getIdSalesShipment();

        if ($idSalesShipment !== null) {
            $salesShipmentEntity = $this->getFactory()
                ->createSalesShipmentQuery()
                ->findOneByIdSalesShipment($idSalesShipment);
        }

        if ($salesShipmentEntity === null) {
            $salesShipmentEntity = new SpySalesShipment();
        }

        $shipmentEntityMapper = $this->getFactory()->createShipmentMapper();
        $salesShipmentEntity = $shipmentEntityMapper
            ->mapShipmentTransferToShipmentEntity($shipmentTransfer, $salesShipmentEntity);
        $salesShipmentEntity = $this->getFactory()->createShipmentOrderMapper()
            ->mapOrderTransferToShipmentEntity($orderTransfer, $salesShipmentEntity);

        if ($expenseTransfer !== null) {
            $salesShipmentEntity = $this->getFactory()->createShipmentExpenseMapper()
                ->mapExpenseTransferToShipmentEntity($expenseTransfer, $salesShipmentEntity);
        }

        $salesShipmentEntity->save();

        return $shipmentEntityMapper->mapShipmentEntityToShipmentTransferWithDetails($salesShipmentEntity, $shipmentTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ItemTransfer $itemTransfer
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return void
     */
    public function updateFkShipmentForOrderItem(ItemTransfer $itemTransfer, ShipmentTransfer $shipmentTransfer): void
    {
        $orderItemEntity = $this->getFactory()
            ->createSalesOrderItemQuery()
            ->filterByIdSalesOrderItem($itemTransfer->getIdSalesOrderItem())
            ->findOneOrCreate();

        $orderItemEntity->setFkSalesShipment($shipmentTransfer->getIdSalesShipment());

        $orderItemEntity->save();
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $shipmentMethodTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    public function saveSalesShipmentMethod(ShipmentMethodTransfer $shipmentMethodTransfer): ShipmentMethodTransfer
    {
        $shipmentMethodMapper = $this->getFactory()->createShipmentMethodMapper();

        $shipmentMethodEntity = $shipmentMethodMapper
            ->mapShipmentMethodTransferToShipmentMethodEntity($shipmentMethodTransfer, new SpyShipmentMethod());

        $shipmentMethodEntity->save();

        return $shipmentMethodMapper
            ->mapShipmentMethodEntityToShipmentMethodTransfer($shipmentMethodEntity, new ShipmentMethodTransfer());
    }

    /**
     * @param int $idShipmentMethod
     *
     * @return void
     */
    public function deleteMethodByIdMethod(int $idShipmentMethod): void
    {
        $this->getFactory()
            ->createShipmentMethodQuery()
            ->filterByIdShipmentMethod($idShipmentMethod)
            ->findOne()
            ->delete();
    }

    /**
     * @param \Generated\Shared\Transfer\ExpenseTransfer $expenseTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ExpenseTransfer
     */
    public function saveSalesExpense(ExpenseTransfer $expenseTransfer, OrderTransfer $orderTransfer): ExpenseTransfer
    {
        $expenseMapper = $this->getFactory()->createShipmentExpenseMapper();

        $salesOrderExpenseEntity = $expenseMapper
            ->mapExpenseTransferToOrderSalesExpenseEntity($expenseTransfer, new SpySalesExpense());

        $salesOrderExpenseEntity->setFkSalesOrder($orderTransfer->getIdSalesOrder());
        $salesOrderExpenseEntity->save();

        return $expenseMapper
            ->mapOrderSalesExpenseEntityToExpenseTransfer($salesOrderExpenseEntity, new ExpenseTransfer());
    }
}