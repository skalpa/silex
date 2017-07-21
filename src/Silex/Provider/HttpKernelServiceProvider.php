<?php

namespace Silex\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Silex\AppArgumentValueResolver;
use Silex\CallbackResolver;
use Silex\EventListener\ConverterListener;
use Silex\EventListener\MiddlewareListener;
use Silex\EventListener\StringToResponseListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\HttpHeaderSerializer;

class HttpKernelServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $container)
    {
        $container->set(array(
            'resolver' => array(__CLASS__, 'getControllerResolver'),
            'argument_metadata_factory' => array(__CLASS__, 'getArgumentMetadataFactory'),
            'argument_value_resolvers' => array(__CLASS__, 'getArgumentValueResolvers'),
            'argument_resolver' => array(__CLASS__, 'getArgumentResolver'),
            'kernel' => array(__CLASS__, 'getKernel'),
            'request_stack' => array(__CLASS__, 'getRequestStack'),
            'dispatcher' => array(__CLASS__, 'getDispatcher'),
            'callback_resolver' => array(__CLASS__, 'getCallbackResolver'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber(new ResponseListener($app['charset']));
        $dispatcher->addSubscriber(new MiddlewareListener($app));
        $dispatcher->addSubscriber(new ConverterListener($app['routes'], $app['callback_resolver']));
        $dispatcher->addSubscriber(new StringToResponseListener());

        if (class_exists(HttpHeaderSerializer::class)) {
            $dispatcher->addSubscriber(new AddLinkHeaderListener());
        }
    }

    public static function getControllerResolver($app)
    {
        return new ControllerResolver($app['logger']);
    }

    public static function getArgumentMetadataFactory($app)
    {
        return new ArgumentMetadataFactory();
    }

    public static function getArgumentValueResolvers($app)
    {
        return array_merge(array(new AppArgumentValueResolver($app)), ArgumentResolver::getDefaultArgumentValueResolvers());
    }

    public static function getArgumentResolver($app)
    {
        return new ArgumentResolver($app['argument_metadata_factory'], $app['argument_value_resolvers']);
    }

    public static function getKernel($app)
    {
        return new HttpKernel($app['dispatcher'], $app['resolver'], $app['request_stack'], $app['argument_resolver']);
    }

    public static function getRequestStack()
    {
        return new RequestStack();
    }

    public static function getDispatcher()
    {
        return new EventDispatcher();
    }

    public static function getCallbackResolver($app)
    {
        return new CallbackResolver($app);
    }
}
