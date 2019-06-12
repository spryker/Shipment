<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Shipment\Persistence;

use Orm\Zed\Sales\Persistence\SpySalesOrderItemQuery;
use Orm\Zed\Sales\Persistence\SpySalesOrderQuery;
use Orm\Zed\Sales\Persistence\SpySalesShipmentQuery;
use Orm\Zed\Shipment\Persistence\SpyShipmentCarrierQuery;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery;
use Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentExpanseMapper;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentExpanseMapperInterface;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMapper;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMapperInterface;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMethodMapper;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMethodMapperInterface;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentOrderMapper;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentOrderMapperInterface;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentTaxSetMapper;
use Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentTaxSetMapperInterface;

/**
 * @method \Spryker\Zed\Shipment\ShipmentConfig getConfig()
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentQueryContainerInterface getQueryContainer()
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentEntityManagerInterface getEntityManager()
 * @method \Spryker\Zed\Shipment\Persistence\ShipmentRepositoryInterface getRepository()
 */
class ShipmentPersistenceFactory extends AbstractPersistenceFactory
{
    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentCarrierQuery
     */
    public function createShipmentCarrierQuery()
    {
        return SpyShipmentCarrierQuery::create();
    }

    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodQuery
     */
    public function createShipmentMethodQuery()
    {
        return SpyShipmentMethodQuery::create();
    }

    /**
     * @return \Orm\Zed\Sales\Persistence\SpySalesShipmentQuery
     */
    public function createSalesShipmentQuery()
    {
        return SpySalesShipmentQuery::create();
    }

    /**
     * @return \Orm\Zed\Sales\Persistence\SpySalesOrderItemQuery
     */
    public function createSalesOrderItemQuery(): SpySalesOrderItemQuery
    {
        return SpySalesOrderItemQuery::create();
    }

    /**
     * @return \Orm\Zed\Shipment\Persistence\SpyShipmentMethodPriceQuery
     */
    public function createShipmentMethodPriceQuery()
    {
        return SpyShipmentMethodPriceQuery::create();
    }

    /**
     * @return \Orm\Zed\Sales\Persistence\SpySalesOrderQuery
     */
    public function createSalesOrderQuery(): SpySalesOrderQuery
    {
        return SpySalesOrderQuery::create();
    }

    /**
     * @return \Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMapperInterface
     */
    public function createShipmentMapper(): ShipmentMapperInterface
    {
        return new ShipmentMapper();
    }

    /**
     * @return \Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentMethodMapperInterface
     */
    public function createShipmentMethodMapper(): ShipmentMethodMapperInterface
    {
        return new ShipmentMethodMapper();
    }

    /**
     * @return \Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentTaxSetMapperInterface
     */
    public function createTaxSetMapper(): ShipmentTaxSetMapperInterface
    {
        return new ShipmentTaxSetMapper();
    }

    /**
     * @return \Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentExpanseMapperInterface
     */
    public function createShipmentExpanseMapper(): ShipmentExpanseMapperInterface
    {
        return new ShipmentExpanseMapper();
    }

    /**
     * @return \Spryker\Zed\Shipment\Persistence\Propel\Mapper\ShipmentOrderMapperInterface
     */
    public function createShipmentOrderMapper(): ShipmentOrderMapperInterface
    {
        return new ShipmentOrderMapper();
    }
}
