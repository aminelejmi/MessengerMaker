<?php

/*
 * This file is part of the AmineLejmi/MessengerMaker bundle.
 *
 * (c) Mohamed Amine LEJMI <lejmi.amine@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AmineLejmi\MessengerMaker;

use Symfony\Component\HttpKernel\Bundle\Bundle;


class MessengerMakerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}