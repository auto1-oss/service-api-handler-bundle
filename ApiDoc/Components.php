<?php

declare(strict_types=1);

namespace Auto1\ServiceAPIHandlerBundle\ApiDoc;

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
}
