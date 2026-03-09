<?php

namespace App\Domain\IndustryRestaurant\Enums;

enum RestaurantTableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Cleaning = 'cleaning';
}
