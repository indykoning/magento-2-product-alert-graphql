<?php

declare(strict_types=1);

namespace Niks\ProductAlertGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;


class Stock implements ResolverInterface
{
    public function __construct(
        protected \Magento\Catalog\Api\ProductRepositoryInterface $_productRepository,
        protected \Magento\Customer\Model\Customer $_customer,
        protected \Magento\Store\Model\StoreManagerInterface $_storeManager,
        protected \Magento\ProductAlert\Model\Stock $_stock
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($args['input']['product_id']) || empty($args['input']['product_id'])) {
            throw new GraphQlInputException(__('Product id is required.'));
        }

        if (!isset($args['input']['email']) || empty(trim($args['input']['email']))) {
            throw new GraphQlInputException(__('Email address is required.'));
        }

        try {
            $_product = $this->_productRepository->getById($args['input']['product_id']);
            $stockModel = $this->_stock
                ->setProductId($_product->getId())
                ->setWebsiteId($this->_storeManager->getStore()->getWebsiteId())
                ->setStoreId($this->_storeManager->getStore()->getId())
                ->setParentId($args['input']['product_id']);

            $customer = $this->_customer;
            $customer->setWebsiteId($this->_storeManager->getWebsite()->getId());
            $customer->loadByEmail($args['input']['email']);

            $stockCollection = $this->_stock->getCollection()
                ->addWebsiteFilter($this->_storeManager->getWebsite()->getId())
                ->addFieldToFilter('product_id', $args['input']['product_id'])
                ->addStatusFilter(0)
                ->setCustomerOrder();

            if (!$customer->getId()) {
                $stockModel->setEmail($args['input']['email']);
                $stockCollection->addFieldToFilter('email', $args['input']['email']);
            } else {
                $stockModel->setCustomerId($customer->getId());
                $stockCollection->addFieldToFilter('customer_id', $customer->getId());
            }

            if ($stockCollection->getSize() > 0) {
                $stockModel->deleteCustomer($customer->getId());

                return [
                    'message' => "You've succesfully unsubscribed from this product."
                ];
            } else {
                $stockModel->save();

                return [
                    'message' => 'Alert subscription has been saved',
                    'id' => $stockModel->getId()
                ];
            }
        } catch (\Exception $e) {
            throw new GraphQlInputException(__("The alert subscription couldn't update at this time. Please try again later."));
        }
    }
}
