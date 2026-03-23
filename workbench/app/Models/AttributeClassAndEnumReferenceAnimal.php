<?php

namespace App\Models;

use App\Attributes\ModelInfo;
use App\Enums\AnimalType;
use App\Interfaces\AnimalInterface;

#[ModelInfo(ParentAnimal::class, AnimalType::Dog)]
class AttributeClassAndEnumReferenceAnimal implements AnimalInterface
{
}
