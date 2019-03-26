<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Business\Shipment;

use Generated\Shared\Transfer\AddressTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\ShipmentMethodTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;
use Orm\Zed\Sales\Persistence\Base\SpySalesOrderItem;
use Orm\Zed\Sales\Persistence\SpySalesOrderAddress;
use Orm\Zed\Sales\Persistence\SpySalesShipment;
use Orm\Zed\Shipment\Persistence\Map\SpyShipmentMethodTableMap;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethod;
use Propel\Runtime\Collection\ObjectCollection;
use Spryker\Service\Shipment\ShipmentServiceInterface;
use Spryker\Zed\Shipment\Business\OrderItem\OrderItemGrouperInterface;
use Spryker\Zed\Shipment\Dependency\Facade\ShipmentToSalesFacadeInterface;
use Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface;

class ShipmentOrderHydrate implements ShipmentOrderHydrateInterface
{
    /**
     * @var \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface
     */
    protected $shipmentRepository;

    /**
     * @var \Spryker\Service\Shipment\ShipmentServiceInterface
     */
    protected $shipmentService;

    /**
     * @var \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToSalesFacadeInterface
     */
    protected $salesFacade;

    /**
     * @param \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface $shipmentRepository,
     * @param \Spryker\Service\Shipment\ShipmentServiceInterface $shipmentService
     * @param \Spryker\Zed\Shipment\Dependency\Facade\ShipmentToSalesFacadeInterface $salesFacade
     */
    public function __construct(
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentServiceInterface $shipmentService,
        ShipmentToSalesFacadeInterface $salesFacade
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentService = $shipmentService;
        $this->salesFacade = $salesFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    public function hydrateOrderWithShipment(OrderTransfer $orderTransfer)
    {
        $orderTransfer->requireIdSalesOrder();
        $idSalesOrder = $orderTransfer->getIdSalesOrder();

        if ($this->isShipmentHydrated($orderTransfer)) {
            return $orderTransfer;
        }

        $shipmentTransfers = $this->getShipmentTransfersByOrder($orderTransfer);
        if (count($shipmentTransfers) === 0) {
            return $orderTransfer;
        }

        if ($this->isMultiShipmentOrder($shipmentTransfers, $orderTransfer) === false) {
            $orderTransfer = $this->hydrateMultiShipmentMethodToOrderTransfer($shipmentTransfers, $orderTransfer);
        } else {
            $orderTransfer = $this->hydrateShipmentMethodToOrderTransfer($shipmentTransfers, $orderTransfer);
        }

        $orderTransfer = $this->setShipmentToOrderExpenses($orderTransfer);

        /**
         * @todo Move to SalesOrderHydrator.
         */
        $orderTransfer = $this->setUniqueOrderItems($orderTransfer);

        $orderTransfer = $this->setOrderShipmentGroups($orderTransfer);

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    protected function isShipmentHydrated(OrderTransfer $orderTransfer): bool
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            if ($itemTransfer->getShipment() === null
                || $itemTransfer->getShipment()->getMethod() === null
                || $itemTransfer->getShipment()->getMethod()->getIdShipmentMethod() === null
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return array|\Generated\Shared\Transfer\ShipmentTransfer[]
     * @throws \Spryker\Zed\Propel\Business\Exception\AmbiguousComparisonException
     */
    protected function getShipmentTransfersByOrder(OrderTransfer $orderTransfer): array
    {
        $shipmentTransfers = $this->shipmentRepository->findShipmentTransfersByOrder($orderTransfer);
        $shipmentMethodTransfers = $this->shipmentRepository->findShipmentMethodTransfersByShipment($shipmentTransfers);
        return $this->getMappedShipmentTransfersToShipmentMethodTransfers($shipmentTransfers, $shipmentMethodTransfers);
    }


    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    protected function isMultiShipmentOrder(iterable $shipmentTransfers, OrderTransfer $orderTransfer): bool
    {
        if (count($shipmentTransfers) > 1) {
            return true;
        }

        foreach ($orderTransfer->getItems() as $itemTransfer) {
            if ($itemTransfer->getShipment() === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function hydrateShipmentMethodToOrderTransfer(
        iterable $shipmentTransfers,
        OrderTransfer $orderTransfer
    ): OrderTransfer {
        /** @var \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer */
        $shipmentTransfer = current($shipmentTransfers);
        $orderTransfer = $this->addShipmentToOrderItems($orderTransfer, $shipmentTransfer);

        return $orderTransfer;
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function hydrateMultiShipmentMethodToOrderTransfer(
        iterable $shipmentTransfers,
        OrderTransfer $orderTransfer
    ): OrderTransfer {
        foreach ($shipmentTransfers as $shipmentTransfer) {
            $orderTransfer = $this->addShipmentMethodToOrderItemsByShipment($orderTransfer, $shipmentTransfer);
        }

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     */
    protected function addShipmentToOrderItems(OrderTransfer $orderTransfer, ShipmentTransfer $shipmentTransfer): OrderTransfer
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $itemTransfer->setShipment($shipmentTransfer);
        }

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     */
    protected function addShipmentMethodToOrderItemsByShipment(OrderTransfer $orderTransfer, ShipmentTransfer $shipmentTransfer): OrderTransfer
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $itemShipmentTransfer = $itemTransfer->getShipment();
            $shipmentMethodTransfer = $itemShipmentTransfer->getMethod();
            if ($shipmentMethodTransfer->getName() === $shipmentTransfer->getMethod()->getName()
                && $itemShipmentTransfer->getShippingAddress()->getIdSalesOrderAddress() === $shipmentTransfer->getShippingAddress()->getIdSalesOrderAddress()
                && $itemShipmentTransfer->getCarrier()->getName() === $shipmentTransfer->getCarrier()->getName()
                && $itemShipmentTransfer->getRequestedDeliveryDate() === $shipmentTransfer->getRequestedDeliveryDate()
            ) {
                $shipmentMethodTransfer = $this->mapShipmentTransferToShipmentMethodTransfer($shipmentMethodTransfer, $shipmentTransfer);
                continue;
            }
        }

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function setShipmentToOrderExpenses(OrderTransfer $orderTransfer): OrderTransfer
    {
        foreach ($orderTransfer->getExpenses() as $expenseTransfer) {
            foreach ($orderTransfer->getItems() as $itemTransfer) {
                if ($itemTransfer->getShipment()->getMethod()->getFkSalesExpense() === $expenseTransfer->getIdSalesExpense()) {
                    $expenseTransfer->setShipment($itemTransfer->getShipment());
                }
            }
        }

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function setOrderShipmentGroups(OrderTransfer $orderTransfer): OrderTransfer
    {
        $shipmentGroups = $this->shipmentService->groupItemsByShipment($orderTransfer->getItems());

        return $orderTransfer->setShipmentGroups($shipmentGroups);
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function setUniqueOrderItems(OrderTransfer $orderTransfer): OrderTransfer
    {
        $uniqueOrderItems = $this->salesFacade->getUniqueOrderItems($orderTransfer->getItems());

        return $orderTransfer->setItems($uniqueOrderItems->getItems());
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentTransfer[] $shipmentTransfers
     * @param iterable|\Generated\Shared\Transfer\ShipmentMethodTransfer[] $shipmentMethodTransfers
     *
     * @return \Generated\Shared\Transfer\ShipmentTransfer[]
     */
    protected function getMappedShipmentTransfersToShipmentMethodTransfers(
        iterable $shipmentTransfers,
        iterable $shipmentMethodTransfers
    ): array {
        if (count($shipmentMethodTransfers) === 0 || count($shipmentTransfers) === 0) {
            return $shipmentTransfers;
        }

        foreach ($shipmentTransfers as $shipmentTransfer) {
            $shipmentMethodTransfer = $this->findShipmentMethodTransferByName($shipmentMethodTransfers, $shipmentTransfer);
            if ($shipmentMethodTransfer === null) {
                continue;
            }

            $shipmentMethodTransfer = $this->mapShipmentTransferToShipmentMethodTransfer($shipmentMethodTransfer, $shipmentTransfer);
            $shipmentTransfer->setMethod($shipmentMethodTransfer);
        }

        return $shipmentTransfers;
    }

    /**
     * @param iterable|\Generated\Shared\Transfer\ShipmentMethodTransfer[] $shipmentMethodTransfers
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer|null
     */
    protected function findShipmentMethodTransferByName(iterable $shipmentMethodTransfers, ShipmentTransfer $shipmentTransfer): ?ShipmentMethodTransfer
    {
        foreach ($shipmentMethodTransfers as $shipmentMethodTransfer) {
            if ($shipmentTransfer->getMethod()->getName() === $shipmentMethodTransfer->getName()) {
                return $shipmentMethodTransfer;
            }
        }

        return null;
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentMethodTransfer $shipmentMethodTransfer
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentMethodTransfer
     */
    protected function mapShipmentTransferToShipmentMethodTransfer(ShipmentMethodTransfer $shipmentMethodTransfer, ShipmentTransfer $shipmentTransfer): ShipmentMethodTransfer
    {
        return $shipmentMethodTransfer->fromArray($shipmentTransfer->getMethod()->modifiedToArray(), true);
    }
}