<?php

namespace LCavero\DoctrinePaginatorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class DoctrinePaginatorBundle extends Bundle
{
    public function getContainerExtension()
    {
        return "lcavero_doctrine_paginator";
    }
}
