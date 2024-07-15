<?php

/*
 * This file is part of the AmineLejmi/MessengerMaker bundle.
 *
 * (c) Mohamed Amine LEJMI <lejmi.amine@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AmineLejmi\MessengerMaker\Maker;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @method string getCommandDescription()
 */
class MakeMessengerEvent extends AbstractMakeMessengerMessage
{

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
    }

    public static function getCommandName(): string
    {
        return 'make:messenger:event';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new messenger event and handler';
    }

    public static function getType(): string
    {
        return self::TYPE_EVENT;
    }

}
