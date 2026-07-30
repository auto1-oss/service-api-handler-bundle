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

## multipart/form-data endpoints
Endpoints declared with `requestFormat: 'multipart'` accept `multipart/form-data` requests.
Text fields, attributes and query parameters are merged into the request DTO as usual;
uploaded files are exposed as `Auto1\ServiceAPIComponentsBundle\Multipart\UploadedFileStream` properties.

Constraints:
* Only `POST` is supported — PHP does not parse multipart bodies for other HTTP methods; any other method is rejected with `400 Bad Request`.
  The *wire* method is what counts: a wire `POST` carrying a `_method`/`X-HTTP-METHOD-OVERRIDE` override is accepted, since PHP has already parsed its body.
* The `Content-Type` mime type is matched case-insensitively; `application/x-www-form-urlencoded` is also accepted
  (PHP parses both form mime types identically, and `Request::create()`/BrowserKit default POST bodies to urlencoded even when files are attached).
* A non-empty form body that PHP could not parse (e.g. `post_max_size` exceeded) is rejected with `400 Bad Request` instead of arriving as a silently empty payload.
* A PSR-17 `Psr\Http\Message\StreamFactoryInterface` service must be registered in the container.
  Install a PSR-7 implementation (e.g. `nyholm/psr7` or `guzzlehttp/psr7`) and register its stream factory.
  This is enforced at container compile time: if the application serves a multipart endpoint
  and no stream factory is available, the container fails to build with a `ConfigurationException`.
  Compile-time enforcement covers endpoints wired through the generated `endpoints.yaml` and the
  `*Controller::*Action` convention; manually-routed handlers (e.g. `__invoke` controllers) are the
  integrator's responsibility and fail at runtime with a descriptive `LogicException` instead.

```yaml
uploadDocument:
    method:        'POST'
    baseUrl:       '%auto1.api.url%'
    path:          '/v1/document'
    requestFormat: 'multipart'
    requestClass:  'App\Request\UploadDocumentRequest'
    responseClass: 'App\Response\Document'
```

## Custom request data extractors
The request payload is built by the first `RequestDataExtractorInterface` implementation
whose `supports()` returns `true` for the endpoint, evaluated in descending priority.

Implementations are autoconfigured: any service implementing
`Auto1\ServiceAPIHandlerBundle\ArgumentResolver\RequestDataExtractor\RequestDataExtractorInterface`
is tagged with `auto1.api_handler.request_data_extractor` automatically at the default priority `0`.

The bundle registers two extractors of its own:
* `multipart` (priority `100`) — handles `requestFormat: 'multipart'` endpoints.
* `default` (priority `-100`) — always-true fallback that decodes the raw body.

To control the order explicitly, tag the service manually:
```yaml
App\Request\CsvRequestDataExtractor:
    tags:
        - { name: 'auto1.api_handler.request_data_extractor', priority: 50 }
```
A priority at or below `-100` is unreachable — the fallback wins first. Verify the effective
order with:
```bash
bin/console debug:container --tag=auto1.api_handler.request_data_extractor
```

## Upgrade note
`ServiceRequestResolver::__construct()` signature changed: the first argument is now a
`DenormalizerInterface` (was `SerializerInterface`) and a required 4th argument (the tagged
iterator of request data extractors) was added. Applications decorating or overriding the
`auto1.api_handler.argument_resolver.service_request` service definition must be updated.

## Swagger generation
For `symfony:>=6.0` and `nelmio/api-doc-bundle:>=4.0` swagger json file is generated in OpenApi v3 format `"openapi": "3.0.0"`.
For previous versions of `symfony` and `nelmio/api-doc-bundle` swagger json file is generated in Swagger V2 format `"swagger": "2.0"`.

## Debug
```bash
bin/console c:c && bin/console debug:router --show-controllers
```

For more info - have a look at [service-api-components-bundle](https://github.com/auto1-oss/service-api-components-bundle) usage:
