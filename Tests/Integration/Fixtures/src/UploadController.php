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

/*
 * Not autoloaded (see exclude-from-classmap): EndpointRouterCompilerPass discovers this file
 * by scanning <kernel.project_dir>/src and loads it with require_once, mirroring how consumer
 * applications' controllers are found.
 */
class UploadController
{
    public function uploadAction(UploadDocumentRequest $request): void
    {
    }
}
