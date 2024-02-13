## Installation

### Register required Bundles:
```php
    Auto1\ServiceAPIComponentsBundle\Auto1ServiceAPIComponentsBundle::class => ['all' => true],
    Auto1\ServiceAPIHandlerBundle\Auto1ServiceAPIHandlerBundle::class => ['all' => true],
```

### config/routing.yml
```yaml
endpoints:
    resource: "@Auto1ServiceAPIHandlerBundle/Resources/config/routing.yml"
```

## Description
Use Endpoints specifications to handle symfony request flow.

Prepares `RequestDTO` from `$_GLOBALS` and serializes response from `ServiceResponse(ResponseDTO, HTTP_CODE)`

## Controller
* Controllers must be tagged with `controller.service_arguments` and suffixed with `Controller`
* Action methods must be suffixed with `Action`

## ServiceResponse
* Fully imitates, but not implements behaviour of `HttpFoundation\Response` (JsonResponse)
* Agnostic to the response format, and performs serialization after being returned from the controller

## Example of EP definition (yaml): 
```yaml
# CarLead
getCarLeadByVin:
    method:        'GET'
    baseUrl:       '%auto1.api.url%'
    path:          '/v1/carlead/vin/{vin}'
    requestClass:  'Auto1\ServiceDTOCollection\CarLead\CarLeadRead\Request\GetCarLeadByVinRequest'
    responseClass: 'Auto1\ServiceDTOCollection\CarLead\CarLeadRead\Response\CarLead'
```

## Example of ServiceRequest implementation:
```php
class GetCarLeadByVinRequest implements ServiceRequestInterface
{
    private $vin;

    public function setVin(string $vin): self
    {
        $this->vin = $vin;

        return $this;
    }

    public function getVin()
    {
        return $this->vin;
    }
}
```

## Example of EP implementation: 
```php
use Auto1\ServiceAPIHandlerBundle\Response\ServiceResponse;
use Auto1\ServiceDTOCollection\CarLead\CarLeadRead\Request\GetCarLeadByVinRequest;
use Auto1\ServiceDTOCollection\CarLead\CarLeadRead\Response\CarLead;

class MyController {
   
    public function carLeadByVinAction(GetCarLeadByVinRequest $carLeadRequestDTO): ServiceResponse
    {
        /** @var CarLead $carLead */
        $carLead = $this->...->find($carLeadRequestDTO->getVin());
    
        return new ServiceResponse(
            $carLead,
            200
        );
    }
}
```

## Swagger generation
For `symfony:>=6.0` and `nelmio/api-doc-bundle:>=4.0` swagger json file is generated in OpenApi v3 format `"openapi": "3.0.0"`.
For previous versions of `symfony` and `nelmio/api-doc-bundle` swagger json file is generated in Swagger V2 format `"swagger": "2.0"`.

## Debug
```bash
bin/console c:c && bin/console debug:router --show-controllers
```

For more info - have a look at [service-api-components-bundle](https://github.com/auto1-oss/service-api-components-bundle) usage:
