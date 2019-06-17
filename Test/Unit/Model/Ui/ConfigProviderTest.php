<?php

/**
* Copyright © 2018 Cointopay. All rights reserved.
* See COPYING.txt for license details.
* @author Ali Usman <mealiusman@gmail.com>
*/

namespace Cointopay\PaymentGateway\Test\Unit\Model\Ui;

use Cointopay\PaymentGateway\Gateway\Http\Client\ClientMock;
use Cointopay\PaymentGateway\Model\Ui\ConfigProvider;

class ConfigProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetConfig()
    {
        $configProvider = new ConfigProvider();

        static::assertEquals(
            [
                'payment' => [
                    ConfigProvider::CODE => [
                        'transactionResults' => [
                            ClientMock::SUCCESS => __('Success'),
                            ClientMock::FAILURE => __('Fraud')
                        ]
                    ]
                ]
            ],
            $configProvider->getConfig()
        );
    }
}
