<?php
namespace Umg\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Pimcore\Event\Model\DataObjectEvent;
use Psr\Http\Client\ClientExceptionInterface;
use Shopify\Auth\FileSessionStorage;
use Shopify\Auth\OAuth;
use Shopify\Clients\Graphql;
use Shopify\Context;
use Shopify\Exception\CookieNotFoundException;
use Shopify\Exception\MissingArgumentException;
use Shopify\Exception\UninitializedContextException;
use Shopify\Utils;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;
use Shopify\Auth\OAuthCookie;


// This guy is tightly coupled to the PostUpdateListener
 class ProductFields
 {
	protected string $pName = "";
	protected float $pPrice = 0.0;
	protected string $pType = "";
	protected string $pSku = "075090851230";
	protected LoggerInterface $logger;
	
        public function __construct(string $Name, float $Price, string $Type,LoggerInterface $pLogger){
	    $this->logger = $pLogger;
            $this->pName = $Name;
	    $this->pPrice = $Price;
	    $this->pType = $Type;
	    $this->logger->debug('Name: ' . print_r($this->pName, true) .
	                         ' Price: ' . print_r($this->pPrice, true) .
	                         ' Type: ' . print_r($this->pType, true));
	}
// I like to see my queries, even when substituting parameters.  
        public function getGraphqlQuery() : string {
	    $pName = $this->pName;
	    $pType = $this->pType;
	    $pPrice = $this->pPrice;
// Leave the heredoc query code unindented to allow quick id of queries.
// Pricing and sku are already available in variants.  This makes sense from an e-comm pov.
$query = <<<QUERY
  mutation {
    productCreate(input: {title: "$pName", productType: "$pType", variants: {price: "$pPrice",sku: "075090851230"}, vendor: "JadedPixel"}) {
      product {
        id
      }
    }
  }
QUERY;
            return $query;	      		
	}
     //Still need to validate query and then process results after presenting to shopify.
        public function getIsProductDupQuery() : string {
	    $pSku = $this->pSku;
// Leave the heredoc query code unindented to allow quick id of queries.	    
$query = <<<QUERY
{
  productVariants(first: 250, query: "sku:$pSku") {
    edges {
      cursor
      node {
        id
        sku
      }
    }
  }
}
QUERY;
            return $query;	      		
        }
     
 }

 class PostUpdateListener
 {

     protected LoggerInterface $logger;

     public function __construct(LoggerInterface $logger) {
         $this->logger = $logger;
     }

     public function __invoke(DataObjectEvent $event): bool
     {

         $object = $event->getObject();
	 if ($object instanceof \Pimcore\Model\DataObject\Product) {

	     $fields = $this->getProductFields($object);
// This should be persisted in a database or a key/value-store.	     
	     $sess_storage = new FileSessionStorage('/var/tmp/shopsessions');
	     $this->logger->debug('$sess_storage: ' . print_r($sess_storage, true));
// All of the following secrets should be persisted in a 'Secrets' store (K8?)
             Context::initialize(
                 apiKey: $_ENV['SHOPIFY_API_KEY'],
                 apiSecretKey: $_ENV['SHOPIFY_API_SECRET_KEY'],
                 scopes: $_ENV['SHOPIFY_APP_SCOPES'],
                 hostName: $_ENV['SHOPIFY_APP_HOST_NAME'],
                 sessionStorage: $sess_storage,
                 apiVersion: '2023-01',
                 isEmbeddedApp: true,
                 isPrivateApp: false,
         	 );
	     // of all of the methods I considered, graphql was cleanest.	 
	     $dup_query = $fields->getIsProductDupQuery();
	     $query = $fields->getGraphqlQuery();
             $this->logger->debug('dup_query: ' . print_r($dup_query, true));
             $this->logger->debug('query: ' . print_r($query, true));
             $client = new Graphql($_ENV['SHOPIFY_APP_HOST_NAME'],
	                           $_ENV['SHOPIFY_ADMIN_API_ACCESS_TOKEN']);
	     //Some things that still bug me are that any edit of a product results in
	     //an update event.  I would like to filter on update type, but instead, I'm
	     //going to move forward with dupe management via sku.
	     $response = $client->query(["query" => $dup_query]);
             $this->logger->debug('dup_query response: ' . print_r($response, true));
	     $response = $client->query(["query" => $query]);
             $this->logger->debug('query response: ' . print_r($response, true));
	     return true;
        }

	return false;
     }
     
     public function getProductFields($object) : ProductFields {
            return new ProductFields($object->getName(),
                                  $object->getPrice(),
                                  $object->getMedia_type(),
				  $this->logger);
     }
}