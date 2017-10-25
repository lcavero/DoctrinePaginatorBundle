<?php

namespace LCavero\DoctrinePaginatorBundle;

use LCavero\DoctrinePaginatorBundle\DependencyInjection\DoctrinePaginatorExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DoctrinePaginatorBundle extends Bundle
{
    public function getContainerExtension()
    {
        if(null == $this->extension){
            return new DoctrinePaginatorExtension();
        }

        return $this->extension;
    }
}
