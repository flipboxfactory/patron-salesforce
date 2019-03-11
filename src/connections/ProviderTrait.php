<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce
 */

namespace flipbox\patron\salesforce\connections;

use flipbox\patron\queries\ProviderQuery;
use Stevenmaguire\OAuth2\Client\Provider\Salesforce;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait ProviderTrait
{
    /**
     * @var mixed
     */
    public $provider;

    /**
     * @param $provider
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return Salesforce
     * @throws \yii\base\InvalidConfigException
     */
    public function getProvider(): Salesforce
    {
        if ($this->provider instanceof Salesforce) {
            return $this->provider;
        }

        // Get provider from settings
        if (null !== ($provider = $this->provider ?? null)) {
            $condition = [
                (is_numeric($provider) ? 'id' : 'handle') => $provider
            ];
            $provider = (new ProviderQuery($condition))->one();
        }

        if (!$provider instanceof Salesforce) {
            $provider = new Salesforce();
        }

        return $provider;
    }
}
