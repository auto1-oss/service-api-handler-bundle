<?php

declare(strict_types=1);

namespace Auto1\ServiceAPIHandlerBundle\ApiDoc;

use OpenApi\Generator;
use Symfony\Component\PropertyInfo\Type;

class Components extends \OpenApi\Annotations\Components
{
    public function validate(array $stack = [], array $skip = [], string $ref = '', $context = null): bool
    {
        /**
         * It's the only way to avoid the error that different responses for different Paths have the same response code
         * <User Warning: Multiple @OA\Response() with the same response="200">
         * https://github.com/zircote/swagger-php/blob/master/src/Annotations/AbstractAnnotation.php#L489
         */
        return true;
    }

    public function jsonSerialize()
    {
        /**
         * If response dto has property `$entities` with type `Entity[]` and with default value as empty array
         * swagger file will be generated with empty array and not with `Entity[]` schema
         * ```
         *  {
         *      "entities": []
         *  }
         * ```
         * This part of code will replace empty array with `Generator::UNDEFINED`
         * and swagger file will generate proper `Entity[]` schema
         *
         * ```
         * {
         *  "entities": [
         *      {
         *          "propertyId": 0,
         *          "branchId": 0,
         *          ....
         *      }
         *  ]
         * }
         * ```
         */
        if (property_exists($this, 'schemas') && is_array($this->schemas)) {
            foreach ($this->schemas as $schema) {
                if (!property_exists($schema, 'properties') || !is_array($schema->properties)) {
                    continue;
                }

                foreach ($schema->properties as $property) {
                    if ($property->type === Type::BUILTIN_TYPE_ARRAY || $property->default === []) {
                        $property->default = Generator::UNDEFINED;
                    }
                }
            }
        }

        return parent::jsonSerialize();
    }
}
