<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/patron-salesforce/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/patron-salesforce/
 */

namespace flipbox\patron\salesforce\connections;

use flipbox\craft\salesforce\helpers\ErrorHelper;
use flipbox\patron\records\Token;
use Flipbox\Skeleton\Helpers\JsonHelper;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait AccessTokenAuthorizationTrait
{
    use AccessTokenTrait;

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     * @throws \yii\base\InvalidConfigException
     */
    public function prepareAuthorizationRequest(
        RequestInterface $request
    ): RequestInterface {
        return $this->addAuthorizationHeader($request);
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     * @throws \yii\base\InvalidConfigException
     */
    protected function addAuthorizationHeader(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(
            'Authorization',
            'Bearer ' . (string)$this->getAccessToken()
        );
    }

    /**
     * @param ResponseInterface $response
     * @param RequestInterface $request
     * @param callable $runner
     * @return ResponseInterface
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     * @throws \flipbox\craft\ember\exceptions\RecordNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    public function handleAuthorizationResponse(
        ResponseInterface $response,
        RequestInterface $request,
        callable $runner
    ): ResponseInterface {

        if ($this->responseIsExpiredToken($response)) {
            $response = $this->refreshAndRetry(
                $request,
                $response,
                $runner
            );
        }

        return $response;
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    protected function responseIsExpiredToken(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 401) {
            return false;
        }

        $data = JsonHelper::decodeIfJson(
            $response->getBody()->getContents()
        );

        return ErrorHelper::hasSessionExpired($data);
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $next
     * @return mixed
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     * @throws \flipbox\craft\ember\exceptions\RecordNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    protected function refreshAndRetry(RequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $refreshToken = $this->getProvider()->getAccessToken('refresh_token', [
            'refresh_token' => $this->getAccessToken()->getRefreshToken()
        ]);

        $this->saveRefreshToken(
            $this->getAccessToken(),
            $refreshToken
        );

        $this->setAccessToken($refreshToken);

        return $next(
            $this->addAuthorizationHeader($request),
            $response
        );
    }

    /**
     * @param AccessTokenInterface $accessToken
     * @param AccessTokenInterface $refreshToken
     * @return bool
     * @throws \flipbox\craft\ember\exceptions\RecordNotFoundException
     */
    protected function saveRefreshToken(AccessTokenInterface $accessToken, AccessTokenInterface $refreshToken): bool
    {
        $record = Token::getOne([
            'accessToken' => $accessToken->getToken()
        ]);
        $record->accessToken = $refreshToken->getToken();
        return $record->save();
    }
}
