<?php

/*
 * This file is part of the auto1-oss/service-api-handler-bundle.
 *
 * (c) AUTO1 Group SE https://www.auto1-group.com
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Auto1\ServiceAPIHandlerBundle\Integration\Fixtures\App;

use BadMethodCallException;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Satisfies the container wiring only — no stream is ever created during compilation.
 */
class StreamFactoryStub implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        throw new BadMethodCallException('Not expected to be called during container compilation.');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new BadMethodCallException('Not expected to be called during container compilation.');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new BadMethodCallException('Not expected to be called during container compilation.');
    }
}
