<?php

namespace App\Models;

use App\Interfaces\AnimalInterface;

#[\AllowDynamicProperties(ParentAnimal::class)]
class AttributeClassReferenceAnimal implements AnimalInterface
{
}
