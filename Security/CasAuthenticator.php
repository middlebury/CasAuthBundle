<?php
// src/AppBundle/Security/TokenAuthenticator.php
namespace PRayno\CasAuthBundle\Security;

use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CasAuthenticator extends AbstractGuardAuthenticator
{
    protected $server_login_url;
    protected $server_validation_url;
    protected $xml_namespace;
    protected $username_attribute;
    protected $query_ticket_parameter;
    protected $query_service_parameter;
    protected $options;

    public function __construct($config)
    {
        $this->server_login_url = $config['server_login_url'];
        $this->server_validation_url = $config['server_validation_url'];
        $this->xml_namespace = $config['xml_namespace'];
        $this->username_attribute = $config['username_attribute'];
        $this->query_service_parameter = $config['query_service_parameter'];
        $this->query_ticket_parameter = $config['query_ticket_parameter'];
        $this->options = $config['options'];
    }

    /**
     * Called on every request. Return whatever credentials you want,
     * or null to stop authentication.
     */
    public function getCredentials(Request $request)
    {
        if ($request->get($this->query_ticket_parameter)) {
            // Validate ticket
            $url = $this->server_validation_url.'?'.$this->query_ticket_parameter.'='.
                $request->get($this->query_ticket_parameter).'&'.
                $this->query_service_parameter.'='.$request->getUri();

            $client = new Client();
            $response = $client->request('GET', $url, $this->options);

            $string = $response->getBody()->getContents();

            $xml = new \SimpleXMLElement($string, 0, false, $this->xml_namespace, true);

            if (isset($xml->authenticationSuccess)) {
                return (array) $xml->authenticationSuccess;
            }
        }

        return null;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if (isset($credentials[$this->username_attribute])) {
            return $userProvider->loadUserByUsername($credentials[$this->username_attribute]);
        } else {
            return null;
        }
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = array(
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        );

        return new JsonResponse($data, 403);
    }

    /**
     * Called when authentication is needed, but it's not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse($this->server_login_url.'?'.$this->query_service_parameter.'='.$request->getUri());
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
