<?php

namespace Socialite\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Socialite\Two\AbstractProvider;
use Socialite\Two\User;
use Socialite\Util\A;

class SlackProvider extends AbstractProvider
{
    /**
     * {@inheritdoc}
     */
    public function getScopes()
    {
        if (count($this->scopes) > 0) {
            return $this->scopes;
        }
        // Provide some default scopes if the user didn't define some.
        return ['identity.basic', 'identity.email', 'identity.team', 'identity.avatar'];
    }

    /**
     * Middleware that throws exceptions for non successful slack api calls
     * "http_error" request option is set to true.
     *
     * @return callable Returns a function that accepts the next handler.
     */
    private function getSlackApiErrorMiddleware()
    {
        return function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                if (empty($options['http_errors'])) {
                    return $handler($request, $options);
                }
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $handler) {
                        $body = json_decode($response->getBody()->getContents(), true);
                        $response->getBody()->rewind();
                        if ($body['ok']) {
                            return $response;
                        }
                        throw RequestException::create($request, $response);
                    }
                );
            };
        };
    }

    /**
     * {@inheritdoc}
     */
    protected function getHttpClient()
    {
        $handler = HandlerStack::create();
        $handler->push($this->getSlackApiErrorMiddleware(), 'slack_api_errors');
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client(['handler' => $handler]);
        }
        return $this->httpClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl(string $state)
    {
        return $this->buildAuthUrlFromBase(
            'https://slack.com/oauth/authorize', $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://slack.com/api/oauth.access';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken(string $token)
    {
        try {
            $response = $this->getHttpClient()->get(
                'https://slack.com/api/users.identity?token=' . $token
            );
        } catch (RequestException $exception) {
            // Getting user informations requires the "identity.*" scopes, however we might want to not add them to the
            // scope list for various reasons. Instead of throwing an exception on this error, we return an empty user.
            if ($exception->hasResponse()) {
                $data = json_decode($exception->getResponse()->getBody(), true);
                if (A::get($data, 'error') === 'missing_scope') {
                    return [];
                }
            }
            throw $exception;
        }
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => A::get($user, 'user.id'),
            'name' => A::get($user, 'user.name'),
            'email' => A::get($user, 'user.email'),
            'avatar' => A::get($user, 'user.image_192'),
            'organization_id' => A::get($user, 'team.id'),
        ]);
    }
}
