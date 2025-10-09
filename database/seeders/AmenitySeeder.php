<?php

namespace Database\Seeders;

use App\Enums\AmenityTypeEnum;
use App\Models\Amenity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $general = ["Bathrooms", "Bedrooms", "Balcony", "Parking Space", "Living Room", "Dining Room", "Kitchen", "Furnished", "Unfurnished", "Air Conditioning", "Heating", "Laundry Room", "Wheelchair Accessible", "Pet Friendly"];
        $special = ["Swimming Pool", "Gym", "Garden", "Playground", "Security", "Elevator", "Fireplace", "Storage Room", "Rooftop Terrace", "Conference Room", "Business Center", "On-site Management", "CCTV", "Gated Community", "Smart Home", "Jacuzzi", "Wardrobe"];

        foreach ($general as $amenity) {
            Amenity::firstOrCreate([
                'name' => $amenity,
                'type' => AmenityTypeEnum::GENERAL->value,
            ]);
        }

        foreach ($special as $amenity) {
            Amenity::firstOrCreate([
                'name' => $amenity,
                'type' => AmenityTypeEnum::SPECIAL->value,
            ]);
        }
    }
}
