<?php

declare(strict_types=1);

namespace Tests\Auto1\ServiceAPIHandlerBundle\ArgumentResolver;

use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

interface DecodeDenormalizeAwareSerializerInterface extends SerializerInterface, DecoderInterface, DenormalizerInterface
{
}
