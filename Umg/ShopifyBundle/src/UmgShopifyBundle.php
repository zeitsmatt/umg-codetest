<?php
 namespace Umg;

 use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
 use Shopify\Exception\MissingArgumentException;
 use Symfony\Component\DependencyInjection\ContainerBuilder;
 use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

 class UmgShopifyBundle extends AbstractPimcoreBundle {
     /**
      * @throws MissingArgumentException
      */
     public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
     {

     }
 }
