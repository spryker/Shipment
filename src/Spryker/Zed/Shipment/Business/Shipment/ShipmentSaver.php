<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Business\Shipment;

use ArrayObject;
use Generated\Shared\Transfer\ExpenseTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\SaveOrderTransfer;
use Generated\Shared\Transfer\ShipmentGroupResponseTransfer;
use Generated\Shared\Transfer\ShipmentGroupTransfer;
use Generated\Shared\Transfer\ShipmentTransfer;
use Spryker\Shared\Shipment\ShipmentConstants;
use Spryker\Zed\Shipment\Business\Checkout\MultiShipmentOrderSaverInterface;
use Spryker\Zed\Shipment\Business\Sanitizer\ExpenseSanitizerInterface;
use Spryker\Zed\Shipment\Business\ShipmentGroup\ShipmentMethodExpanderInterface;

class ShipmentSaver implements ShipmentSaverInterface
{
    /**
     * @var \Spryker\Zed\Shipment\Business\Checkout\MultiShipmentOrderSaverInterface
     */
    protected $shipmentOrderSaver;

    /**
     * @var \Spryker\Zed\Shipment\Business\ShipmentGroup\ShipmentMethodExpanderInterface
     */
    protected $shipmentMethodExpander;

    /**
     * @var \Spryker\Zed\Shipment\Business\Sanitizer\ExpenseSanitizerInterface
     */
    protected $expenseSanitizer;

    /**
     * @param \Spryker\Zed\Shipment\Business\Checkout\MultiShipmentOrderSaverInterface $shipmentOrderSaver
     * @param \Spryker\Zed\Shipment\Business\ShipmentGroup\ShipmentMethodExpanderInterface $shipmentMethodExpander
     * @param \Spryker\Zed\Shipment\Business\Sanitizer\ExpenseSanitizerInterface $expenseSanitizer
     */
    public function __construct(
        MultiShipmentOrderSaverInterface $shipmentOrderSaver,
        ShipmentMethodExpanderInterface $shipmentMethodExpander,
        ExpenseSanitizerInterface $expenseSanitizer
    ) {
        $this->shipmentOrderSaver = $shipmentOrderSaver;
        $this->shipmentMethodExpander = $shipmentMethodExpander;
        $this->expenseSanitizer = $expenseSanitizer;
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentGroupTransfer $shipmentGroupTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentGroupResponseTransfer
     */
    public function saveShipment(
        ShipmentGroupTransfer $shipmentGroupTransfer,
        OrderTransfer $orderTransfer
    ): ShipmentGroupResponseTransfer {
        $saveOrderTransfer = $this->buildSaveOrderTransfer($orderTransfer);
        $shipmentGroupTransfer = $this->setShipmentMethod($shipmentGroupTransfer, $orderTransfer);
        $expenseTransfer = $this->createShippingExpenseTransfer($shipmentGroupTransfer->getShipment(), $orderTransfer);
        $orderTransfer = $this->addShippingExpenseToOrderExpenses($expenseTransfer, $orderTransfer);

        $shipmentGroupTransfer = $this->shipmentOrderSaver
            ->saveOrderShipmentByShipmentGroup($orderTransfer, $shipmentGroupTransfer, $saveOrderTransfer);

        $shipmentGroupResponseTransfer = new ShipmentGroupResponseTransfer();
        $shipmentGroupResponseTransfer->setIsSuccessful(true);
        $shipmentGroupResponseTransfer->setShipmentGroup($shipmentGroupTransfer);

        return $shipmentGroupResponseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\SaveOrderTransfer
     */
    protected function buildSaveOrderTransfer(OrderTransfer $orderTransfer): SaveOrderTransfer
    {
        return (new SaveOrderTransfer())
            ->setOrderItems($orderTransfer->getItems())
            ->setIdSalesOrder($orderTransfer->getIdSalesOrder())
            ->setOrderReference($orderTransfer->getOrderReference())
            ->setOrderExpenses($orderTransfer->getExpenses());
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentTransfer $shipmentTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ExpenseTransfer
     */
    protected function createShippingExpenseTransfer(
        ShipmentTransfer $shipmentTransfer,
        OrderTransfer $orderTransfer
    ): ExpenseTransfer {
        $shipmentMethodTransfer = $shipmentTransfer->getMethod();
        $expenseTransfer = new ExpenseTransfer();

        $expenseTransfer->fromArray($shipmentMethodTransfer->modifiedToArray(), true);
        $expenseTransfer->setFkSalesOrder($orderTransfer->getIdSalesOrder());
        $expenseTransfer->setType(ShipmentConstants::SHIPMENT_EXPENSE_TYPE);
        $this->setPrice(
            $expenseTransfer,
            $shipmentMethodTransfer->getStoreCurrencyPrice(),
            $orderTransfer->getPriceMode()
        );
        $expenseTransfer->setQuantity(1);
        $expenseTransfer->setShipment($shipmentTransfer);

        return $this->expenseSanitizer->sanitizeExpenseSumValues($expenseTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\ExpenseTransfer $expenseTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function addShippingExpenseToOrderExpenses(
        ExpenseTransfer $expenseTransfer,
        OrderTransfer $orderTransfer
    ): OrderTransfer {
        $orderTransfer = $this->removeExistingShippingExpenseFromOrderExpenses($expenseTransfer, $orderTransfer);
        $orderTransfer->addExpense($expenseTransfer);

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ExpenseTransfer $expenseTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\OrderTransfer
     */
    protected function removeExistingShippingExpenseFromOrderExpenses(
        ExpenseTransfer $expenseTransfer,
        OrderTransfer $orderTransfer
    ): OrderTransfer {
        $orderExpensesCollection = new ArrayObject();

        foreach ($orderTransfer->getExpenses() as $orderExpenseTransfer) {
            if ($expenseTransfer->getShipment() === $orderExpenseTransfer->getShipment()) {
                continue;
            }

            $orderExpensesCollection->append($expenseTransfer);
        }

        $orderTransfer->setExpenses($orderExpensesCollection);

        return $orderTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ExpenseTransfer $shipmentExpenseTransfer
     * @param int $price
     * @param string $priceMode
     *
     * @return void
     */
    protected function setPrice(ExpenseTransfer $shipmentExpenseTransfer, int $price, string $priceMode): void
    {
        if ($priceMode === ShipmentConstants::PRICE_MODE_NET) {
            $shipmentExpenseTransfer->setUnitGrossPrice(0);
            $shipmentExpenseTransfer->setUnitPriceToPayAggregation(0);
            $shipmentExpenseTransfer->setUnitPrice($price);
            $shipmentExpenseTransfer->setUnitNetPrice($price);

            return;
        }

        $shipmentExpenseTransfer->setUnitPriceToPayAggregation(0);
        $shipmentExpenseTransfer->setUnitNetPrice(0);
        $shipmentExpenseTransfer->setUnitPrice($price);
        $shipmentExpenseTransfer->setUnitGrossPrice($price);
    }

    /**
     * @param \Generated\Shared\Transfer\ShipmentGroupTransfer $shipmentGroupTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\ShipmentGroupTransfer
     */
    protected function setShipmentMethod(
        ShipmentGroupTransfer $shipmentGroupTransfer,
        OrderTransfer $orderTransfer
    ): ShipmentGroupTransfer {
        $shipmentGroupTransfer->requireShipment();
        $shipmentTransfer = $shipmentGroupTransfer->getShipment();

        $shipmentTransfer->requireMethod();
        $shipmentMethodTransfer = $shipmentTransfer->getMethod();

        $shipmentTransfer->setMethod($this->shipmentMethodExpander->expand($shipmentMethodTransfer, $orderTransfer));

        return $shipmentGroupTransfer;
    }
}