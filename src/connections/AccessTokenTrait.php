<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce/
 */

namespace flipbox\patron\salesforce\connections;

use flipbox\patron\Patron;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait AccessTokenTrait
{
    use ProviderTrait;

    /**
     * @var AccessTokenInterface|null
     */
    private $accessToken;

    /**
     * @param AccessTokenInterface $accessToken
     */
    public function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return AccessTokenInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function getAccessToken(): AccessTokenInterface
    {
        if (!$this->accessToken instanceof AccessTokenInterface) {
            $token = Patron::getInstance()->getTokens([
                'provider' => $this->getProvider()
            ])->one();

            $this->accessToken = $token;
        }

        return $this->accessToken;
    }
}
